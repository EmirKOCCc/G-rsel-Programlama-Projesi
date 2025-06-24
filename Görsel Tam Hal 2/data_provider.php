<?php
// data_provider.php
header('Content-Type: application/json');
require_once 'config.php';

// Gelen istekten sütun adlarını ve tablo adını al
// Bu değerler JavaScript tarafından gönderilecek, ancak güvenlik için burada da tanımlayabiliriz.
// VEYA index.php'deki gibi doğrudan kullanabiliriz. Şimdilik index.php'deki tanımları esas alalım.
$sutun_glikoz = 'glikoz';
$sutun_bmi = 'vki';
$sutun_yas = 'yas';
$sutun_gebelik = 'gebelikSayisi';
$sutun_kan_basinci = 'kanBasinci';
// $sutun_cinsiyet = 'cinsiyet'; // Varsa
$tablo_adi = 'diyabetdb'; // Kendi tablo adınız

// Risk segmentasyon SQL'i (index.php'deki ile aynı olmalı veya oradan alınmalı)
// ÖNEMLİ: KENDİ KRİTERLERİNİZE GÖRE GÜNCELLEYİN!
$risk_segment_sql_case = "
    CASE
        WHEN $sutun_glikoz >= 126 OR $sutun_bmi >= 30 THEN 'Yuksek'
        WHEN ($sutun_glikoz >= 100 AND $sutun_glikoz < 126) OR ($sutun_bmi >= 25 AND $sutun_bmi < 30) THEN 'Orta'
        ELSE 'Dusuk'
    END
";


// Filtreleri al
$filterYas = isset($_GET['yas']) ? $_GET['yas'] : '';
// $filterCinsiyet = isset($_GET['cinsiyet']) ? $_GET['cinsiyet'] : ''; // Varsa
$filterRisk = isset($_GET['risk']) ? $_GET['risk'] : '';

$whereClauses = [];
$params = [];
$types = "";

// Yaş filtresi
if (!empty($filterYas)) {
    if (strpos($filterYas, '-') !== false) {
        list($minYas, $maxYas) = explode('-', $filterYas);
        if (is_numeric(trim($minYas)) && is_numeric(trim($maxYas))) {
            $whereClauses[] = "$sutun_yas BETWEEN ? AND ?";
            $params[] = (int)trim($minYas);
            $params[] = (int)trim($maxYas);
            $types .= "ii";
        }
    } elseif (strpos($filterYas, '>') !== false) {
        $yasVal = (int)trim(str_replace('>', '', $filterYas));
        $whereClauses[] = "$sutun_yas > ?";
        $params[] = $yasVal;
        $types .= "i";
    } elseif (strpos($filterYas, '<') !== false) {
        $yasVal = (int)trim(str_replace('<', '', $filterYas));
        $whereClauses[] = "$sutun_yas < ?";
        $params[] = $yasVal;
        $types .= "i";
    } elseif (is_numeric(trim($filterYas))) {
         $whereClauses[] = "$sutun_yas = ?";
         $params[] = (int)trim($filterYas);
         $types .= "i";
    }
}

// Cinsiyet filtresi (Eğer cinsiyet sütununuz varsa aktif edin)
/*
if (!empty($filterCinsiyet) && isset($sutun_cinsiyet)) {
    $whereClauses[] = "$sutun_cinsiyet = ?";
    $params[] = $filterCinsiyet;
    $types .= "s";
}
*/

// Risk filtresi
if (!empty($filterRisk)) {
    // HAVING clause'u risk_segment_alias üzerinde çalışacak
    // $whereClauses[] kısmına eklenmeyecek, sorgunun sonuna eklenecek
}


$sql_select_part = "SELECT
    $sutun_glikoz,
    $sutun_bmi,
    $sutun_yas,
    $sutun_kan_basinci,
    ($risk_segment_sql_case) as risk_durumu "; // Alias'ı burada tanımlıyoruz

// Eğer cinsiyet varsa ekle
// if(isset($sutun_cinsiyet)) {
// $sql_select_part .= ", $sutun_cinsiyet ";
// }

$sql_from_part = " FROM $tablo_adi";
$sql_where_part = "";

if (count($whereClauses) > 0) {
    $sql_where_part = " WHERE " . implode(" AND ", $whereClauses);
}

$sql_having_part = "";
if (!empty($filterRisk)) {
    $sql_having_part = " HAVING risk_durumu = ?"; // HAVING clause'u burada
    $params[] = $filterRisk; // Parametreyi sona ekle
    $types .= "s"; // Türü sona ekle
}


$response = [
    'genelIstatistikler' => [],
    'riskSegmentasyonu' => [],
    'glikozYasTrendi' => ['labels' => [], 'data' => []],
    'kanBasinciYasTrendi' => ['labels' => [], 'data' => []],
    'korelasyonVerisi' => [],
    'riskGauge' => ['value' => 0, 'total' => 0]
];

// 1. Genel İstatistikler (Filtrelenmiş)
$sql_genel = "SELECT
    COUNT(*) as toplam_hasta,
    ROUND(AVG($sutun_bmi), 2) as ort_bmi,
    ROUND(AVG($sutun_glikoz), 2) as ort_glikoz,
    ROUND(AVG($sutun_gebelik), 2) as ort_gebelik,
    ROUND(AVG($sutun_kan_basinci), 2) as ort_kan_basinci
    $sql_from_part $sql_where_part"; // HAVING burada uygulanmaz, çünkü AVG'ler risk_durumu'na bağlı değil.

// Eğer risk filtresi varsa, genel istatistikler için alt sorgu veya join gerekebilir.
// Şimdilik basit tutalım, risk filtresi genel istatistikleri etkilemesin (veya tüm veritabanını risk_durumu ile filtreleyip sonra avg al).
// Daha doğru bir yaklaşım:
if (!empty($filterRisk)) {
     // HAVING'i WHERE'e dönüştürmek için risk_segment_sql_case'i kullanmalıyız.
     // Bu, sorguyu biraz karmaşıklaştırır. Şimdilik, risk filtresinin genel istatistikleri
     // doğrudan etkilemediğini varsayalım veya tüm veriyi risk_durumuna göre filtreleyip sonra AVG alalım.
     // En basit yol, risk filtresi varsa, risk_segment_sql_case'i WHERE'e eklemek.
     $risk_filter_condition_for_where = "";
     if (!empty($filterRisk)){
        $risk_filter_condition_for_where = " AND ($risk_segment_sql_case) = '$filterRisk' "; // DİKKAT: SQL Injection riski, sanitize edilmeli
     }
      $sql_genel = "SELECT
        COUNT(*) as toplam_hasta,
        ROUND(AVG($sutun_bmi), 2) as ort_bmi,
        ROUND(AVG($sutun_glikoz), 2) as ort_glikoz,
        ROUND(AVG($sutun_gebelik), 2) as ort_gebelik,
        ROUND(AVG($sutun_kan_basinci), 2) as ort_kan_basinci
        FROM (SELECT *, $risk_segment_sql_case as risk_durumu_alias FROM $tablo_adi) as subquery
        $sql_where_part" . ( !empty($filterRisk) ? ( (count($whereClauses) > 0 ? " AND " : " WHERE ") . "risk_durumu_alias = '$filterRisk'" ) : "" );

}


$stmt_genel = $conn->prepare($sql_genel);
if ($stmt_genel) {
    // Genel istatistikler için parametre bağlama (sadece WHERE'den gelenler)
    $genel_params = [];
    $genel_types = "";
    if (!empty($filterYas)) { // Sadece yaş filtresi varsa
        if (strpos($filterYas, '-') !== false) {
            list($minYas, $maxYas) = explode('-', $filterYas);
             if (is_numeric(trim($minYas)) && is_numeric(trim($maxYas))) {
                $genel_params[] = (int)trim($minYas);
                $genel_params[] = (int)trim($maxYas);
                $genel_types .= "ii";
            }
        } elseif (strpos($filterYas, '>') !== false) {
             $genel_params[] = (int)trim(str_replace('>', '', $filterYas));
             $genel_types .= "i";
        } elseif (strpos($filterYas, '<') !== false) {
             $genel_params[] = (int)trim(str_replace('<', '', $filterYas));
             $genel_types .= "i";
        } elseif (is_numeric(trim($filterYas))) {
             $genel_params[] = (int)trim($filterYas);
             $genel_types .= "i";
        }
    }
    // Cinsiyet filtresi için benzer bir mantık (eğer aktifse)

    if (!empty($genel_types)) {
        $stmt_genel->bind_param($genel_types, ...$genel_params);
    }

    $stmt_genel->execute();
    $result = $stmt_genel->get_result();
    if ($result->num_rows > 0) {
        $response['genelIstatistikler'] = $result->fetch_assoc();
    }
    $stmt_genel->close();
} else {
    $response['error_genel'] = "Genel istatistik sorgu hatası: " . $conn->error;
}


// 2. Diyabet Risk Segmentasyonu (Filtrelenmiş)
$sql_risk_segment = "SELECT ($risk_segment_sql_case) as risk_grubu, COUNT(*) as sayi
    $sql_from_part $sql_where_part
    GROUP BY risk_grubu
    $sql_having_part"; // HAVING burada risk_grubu (alias) için çalışmaz, direkt risk_segment_sql_case kullanılmalı veya alt sorgu

// HAVING'i WHERE'e dönüştürerek daha güvenli hale getirelim
$sql_risk_segment_final = "SELECT risk_durumu_alias as risk_grubu, COUNT(*) as sayi
                           FROM (SELECT *, $risk_segment_sql_case as risk_durumu_alias FROM $tablo_adi $sql_where_part) as subquery";
if (!empty($filterRisk)) {
    $sql_risk_segment_final .= " WHERE risk_durumu_alias = ? "; // Parametre olarak eklenecek
}
$sql_risk_segment_final .= " GROUP BY risk_grubu";

$stmt_risk = $conn->prepare($sql_risk_segment_final);
if ($stmt_risk) {
    // Tüm parametreleri (WHERE için olanlar + risk filtresi için olan) birleştir
    $current_params_for_risk = $params; // $params, WHERE clause parametrelerini zaten içeriyor
    // Risk filtresi için olanı $params listesine zaten ekledik (eğer $sql_having_part kullanılsaydı).
    // Yeni sorgu yapısında, risk filtresi için parametre sona eklenecek.
    $final_params_for_risk = $params; // $params'ın son hali ($sql_having_part için olanı içeriyor)
    $final_types_for_risk = $types;   // $types'ın son hali

    if (!empty($final_types_for_risk)) {
      $stmt_risk->bind_param($final_types_for_risk, ...$final_params_for_risk);
    }

    $stmt_risk->execute();
    $result = $stmt_risk->get_result();
    $risk_data = ['labels' => [], 'data' => []];
    $total_for_gauge = 0;
    $high_risk_for_gauge = 0;

    while ($row = $result->fetch_assoc()) {
        $risk_data['labels'][] = $row['risk_grubu'];
        $risk_data['data'][] = (int)$row['sayi'];
        $total_for_gauge += (int)$row['sayi'];
        if ($row['risk_grubu'] == 'Yuksek') {
            $high_risk_for_gauge = (int)$row['sayi'];
        }
    }
    $response['riskSegmentasyonu'] = $risk_data;
    if ($total_for_gauge > 0) {
        $response['riskGauge']['value'] = round(($high_risk_for_gauge / $total_for_gauge) * 100, 1);
    } else {
        $response['riskGauge']['value'] = 0;
    }
    $stmt_risk->close();
} else {
     $response['error_risk_segment'] = "Risk segmentasyon sorgu hatası: " . $conn->error . " | SQL: " . $sql_risk_segment_final;
}


// 3. Trend ve Dağılım Analizleri (Filtrelenmiş)
// Glikoz - Yaş
$sql_glikoz_yas = "SELECT $sutun_yas, AVG($sutun_glikoz) as ort_glikoz
    $sql_from_part $sql_where_part
    GROUP BY $sutun_yas
    $sql_having_part
    ORDER BY $sutun_yas ASC";

$stmt_glikoz_yas = $conn->prepare($sql_glikoz_yas);
if ($stmt_glikoz_yas) {
    if (!empty($types)) {
        $stmt_glikoz_yas->bind_param($types, ...$params);
    }
    $stmt_glikoz_yas->execute();
    $result = $stmt_glikoz_yas->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['glikozYasTrendi']['labels'][] = $row[$sutun_yas];
        $response['glikozYasTrendi']['data'][] = round($row['ort_glikoz'], 2);
    }
    $stmt_glikoz_yas->close();
} else {
    $response['error_glikoz_yas'] = "Glikoz-Yaş sorgu hatası: " . $conn->error;
}


// Kan Basıncı - Yaş
$sql_kanb_yas = "SELECT $sutun_yas, AVG($sutun_kan_basinci) as ort_kan_basinci
    $sql_from_part $sql_where_part
    GROUP BY $sutun_yas
    $sql_having_part
    ORDER BY $sutun_yas ASC";
$stmt_kanb_yas = $conn->prepare($sql_kanb_yas);
if ($stmt_kanb_yas) {
    if (!empty($types)) {
        $stmt_kanb_yas->bind_param($types, ...$params);
    }
    $stmt_kanb_yas->execute();
    $result = $stmt_kanb_yas->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['kanBasinciYasTrendi']['labels'][] = $row[$sutun_yas];
        $response['kanBasinciYasTrendi']['data'][] = round($row['ort_kan_basinci'], 2);
    }
    $stmt_kanb_yas->close();
} else {
    $response['error_kanb_yas'] = "Kan Basıncı-Yaş sorgu hatası: " . $conn->error;
}

// 4. Korelasyon Analizi (Glikoz, BMI, Risk Durumu - Filtrelenmiş)
$sql_korelasyon = "SELECT $sutun_glikoz, $sutun_bmi, ($risk_segment_sql_case) as risk_durumu
    $sql_from_part $sql_where_part $sql_having_part"; // LIMIT 100 ekleyebiliriz çok fazla veri varsa
$stmt_korelasyon = $conn->prepare($sql_korelasyon);
if ($stmt_korelasyon) {
    if (!empty($types)) {
        $stmt_korelasyon->bind_param($types, ...$params);
    }
    $stmt_korelasyon->execute();
    $result = $stmt_korelasyon->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['korelasyonVerisi'][] = [
            'x' => (float)$row[$sutun_glikoz],
            'y' => (float)$row[$sutun_bmi],
            'risk' => $row['risk_durumu']
        ];
    }
    $stmt_korelasyon->close();
} else {
    $response['error_korelasyon'] = "Korelasyon sorgu hatası: " . $conn->error;
}


echo json_encode($response);
$conn->close();
?>