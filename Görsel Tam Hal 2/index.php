<?php
// Veritabanı bağlantı dosyasını dahil et
require_once 'config.php';

// ---- Veritabanı Sütun Adları ve Tablo Adı ----
$sutun_id = 'id'; // Benzersiz hasta ID sütunu
$sutun_glikoz = 'glikoz';
$sutun_bmi = 'vki';
$sutun_yas = 'yas';
$sutun_gebelik = 'gebelikSayisi';
$sutun_kan_basinci = 'kanBasinci'; // Eğer '120/80' gibi string ise parse edilmeli veya ayrı sütunlar kullanılmalı
// Örnek ayrı kan basıncı sütunları (eğer varsa, yoksa $sutun_kan_basinci kullanılır)
// $sutun_sistolik_bp = 'sistolik_bp';
// $sutun_diastolik_bp = 'diastolik_bp';
$sutun_deri_kalinligi = 'deriKalinligi';
$sutun_insulin = 'insulin';
$sutun_diyabet_soygecmis = 'diyabetSoygecmisFonksiyonu';
$tablo_adi = 'fuay_hastanesi_veri';

// ---- Risk Segmentasyonu için SQL CASE İfadesi ----
$risk_segment_sql = "
    CASE
        WHEN $sutun_glikoz >= 126 OR $sutun_bmi >= 30 THEN 'Yuksek'
        WHEN ($sutun_glikoz >= 100 AND $sutun_glikoz < 126) OR ($sutun_bmi >= 25 AND $sutun_bmi < 30) THEN 'Orta'
        ELSE 'Dusuk'
    END AS risk_seviyesi
";

// ---- GENEL FİLTRELEME MANTIĞI (Grafikler ve genel bakış için) ----
$genel_where_clauses = [];
$genel_params = [];
$genel_types = "";

// Genel Yaş Filtresi
$filter_yas_str_genel = trim($_GET['filterYas'] ?? '');
if (!empty($filter_yas_str_genel)) {
    if (strpos($filter_yas_str_genel, '-') !== false) {
        list($min_yas_g, $max_yas_g) = array_map('trim', explode('-', $filter_yas_str_genel));
        if (is_numeric($min_yas_g) && is_numeric($max_yas_g)) {
            $genel_where_clauses[] = "$sutun_yas BETWEEN ? AND ?";
            $genel_params[] = (int)$min_yas_g; $genel_params[] = (int)$max_yas_g; $genel_types .= "ii";
        }
    } elseif (strpos($filter_yas_str_genel, '>') === 0) {
        $yas_val_g = (int)trim(substr($filter_yas_str_genel, 1));
        $genel_where_clauses[] = "$sutun_yas > ?"; $genel_params[] = $yas_val_g; $genel_types .= "i";
    } elseif (strpos($filter_yas_str_genel, '<') === 0) {
        $yas_val_g = (int)trim(substr($filter_yas_str_genel, 1));
        $genel_where_clauses[] = "$sutun_yas < ?"; $genel_params[] = $yas_val_g; $genel_types .= "i";
    } elseif (is_numeric($filter_yas_str_genel)) {
         $genel_where_clauses[] = "$sutun_yas = ?"; $genel_params[] = (int)$filter_yas_str_genel; $genel_types .= "i";
    }
}

// Genel Risk Seviyesi Filtresi
$filter_risk_seviyesi_genel = $_GET['filterRisk'] ?? '';
if (!empty($filter_risk_seviyesi_genel)) {
    if ($filter_risk_seviyesi_genel === 'Yuksek') {
        $genel_where_clauses[] = "($sutun_glikoz >= 126 OR $sutun_bmi >= 30)";
    } elseif ($filter_risk_seviyesi_genel === 'Orta') {
        $genel_where_clauses[] = "(($sutun_glikoz >= 100 AND $sutun_glikoz < 126) OR ($sutun_bmi >= 25 AND $sutun_bmi < 30))";
        $genel_where_clauses[] = "NOT ($sutun_glikoz >= 126 OR $sutun_bmi >= 30)";
    } elseif ($filter_risk_seviyesi_genel === 'Dusuk') {
        $genel_where_clauses[] = "NOT (($sutun_glikoz >= 100 AND $sutun_glikoz < 126) OR ($sutun_bmi >= 25 AND $sutun_bmi < 30))";
        $genel_where_clauses[] = "NOT ($sutun_glikoz >= 126 OR $sutun_bmi >= 30)";
    }
}

$sql_genel_where_condition = ""; // GRAFİKLER ve GENEL İSTATİSTİKLER İÇİN KULLANILACAK
if (!empty($genel_where_clauses)) {
    $sql_genel_where_condition = " WHERE " . implode(" AND ", $genel_where_clauses);
}


// ---- DİNAMİK TABLO İÇİN ÖZEL ARALIK FİLTRELERİ ----
$tablo_ozel_where_clauses = [];
$tablo_ozel_params = [];
$tablo_ozel_types = "";

$filter_min_yas_tablo = trim($_GET['filterMinYasTablo'] ?? '');
$filter_max_yas_tablo = trim($_GET['filterMaxYasTablo'] ?? '');
$filter_min_glikoz_tablo = trim($_GET['filterMinGlikozTablo'] ?? '');
$filter_max_glikoz_tablo = trim($_GET['filterMaxGlikozTablo'] ?? '');
$filter_min_bmi_tablo = trim($_GET['filterMinBmiTablo'] ?? '');
$filter_max_bmi_tablo = trim($_GET['filterMaxBmiTablo'] ?? '');

if (is_numeric($filter_min_yas_tablo)) {
    $tablo_ozel_where_clauses[] = "$sutun_yas >= ?";
    $tablo_ozel_params[] = (int)$filter_min_yas_tablo; $tablo_ozel_types .= "i";
}
if (is_numeric($filter_max_yas_tablo)) {
    $tablo_ozel_where_clauses[] = "$sutun_yas <= ?";
    $tablo_ozel_params[] = (int)$filter_max_yas_tablo; $tablo_ozel_types .= "i";
}
if (is_numeric($filter_min_glikoz_tablo)) {
    $tablo_ozel_where_clauses[] = "$sutun_glikoz >= ?";
    $tablo_ozel_params[] = (float)$filter_min_glikoz_tablo; $tablo_ozel_types .= "d";
}
if (is_numeric($filter_max_glikoz_tablo)) {
    $tablo_ozel_where_clauses[] = "$sutun_glikoz <= ?";
    $tablo_ozel_params[] = (float)$filter_max_glikoz_tablo; $tablo_ozel_types .= "d";
}
if (is_numeric($filter_min_bmi_tablo)) {
    $tablo_ozel_where_clauses[] = "$sutun_bmi >= ?";
    $tablo_ozel_params[] = (float)$filter_min_bmi_tablo; $tablo_ozel_types .= "d";
}
if (is_numeric($filter_max_bmi_tablo)) {
    $tablo_ozel_where_clauses[] = "$sutun_bmi <= ?";
    $tablo_ozel_params[] = (float)$filter_max_bmi_tablo; $tablo_ozel_types .= "d";
}

// ---- TÜM FİLTRELERİ BİRLEŞTİR (Dinamik Tablo için) ----
$dinamik_tablo_final_where_clauses = array_merge($genel_where_clauses, $tablo_ozel_where_clauses);
$dinamik_tablo_final_params = array_merge($genel_params, $tablo_ozel_params);
$dinamik_tablo_final_types = $genel_types . $tablo_ozel_types;

$sql_dinamik_tablo_where_condition = "";
if (!empty($dinamik_tablo_final_where_clauses)) {
    $sql_dinamik_tablo_where_condition = " WHERE " . implode(" AND ", $dinamik_tablo_final_where_clauses);
}


// ---- Veri Çekme Yardımcı Fonksiyonu ----
function fetchData($conn, $sql, $params = [], $types = "") {
    // error_log("DEBUG SQL: " . $sql . " PARAMS: " . json_encode($params) . " TYPES: " . $types);
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        error_log("SQL Prepare Error: " . $conn->error . " Query: " . $sql); return null;
    }
    if (!empty($params) && !empty($types)) {
        if (count($params) !== strlen($types)) {
            error_log("SQL Bind Param Error: Mismatch params and types count. Query: " . $sql);
            $stmt->close(); return null;
        }
        if (!$stmt->bind_param($types, ...$params)) {
            error_log("SQL Bind Param Error: " . $stmt->error . " Query: " . $sql); $stmt->close(); return null;
        }
    }
    if (!$stmt->execute()) {
        error_log("SQL Execute Error: " . $stmt->error . " Query: " . $sql); $stmt->close(); return null;
    }
    $result = $stmt->get_result();
    $stmt->close();
    return $result;
}

// ---- Grafik Renkleri ----
$risk_renkleri_map = [
    'Dusuk' => 'rgba(54, 162, 235, 0.7)', 'DusukBorder' => 'rgba(54, 162, 235, 1)',
    'Orta' => 'rgba(255, 206, 86, 0.7)', 'OrtaBorder' => 'rgba(255, 206, 86, 1)',
    'Yuksek' => 'rgba(255, 99, 132, 0.7)', 'YuksekBorder' => 'rgba(255, 99, 132, 1)'
];



// -----------------------------------------------------------------------------
// VERİ ÇEKME YARDIMCI FONKSİYONU
// -----------------------------------------------------------------------------

// -----------------------------------------------------------------------------
// 1. GENEL İSTATİSTİKLER
// -----------------------------------------------------------------------------
$sql_genel_istatistikler = "SELECT
                                COUNT(*) AS toplam_hasta,
                                ROUND(AVG(NULLIF({$sutun_bmi}, 0)), 2) AS ort_bmi,
                                ROUND(AVG(NULLIF({$sutun_glikoz}, 0)), 2) AS ort_glikoz,
                                ROUND(AVG({$sutun_gebelik}), 2) AS ort_gebelik,
                                ROUND(AVG(NULLIF({$sutun_kan_basinci}, 0)), 2) AS ort_kan_basinci
                            FROM {$tablo_adi}
                            {$sql_genel_where_condition}";

$result_genel = fetchData($conn, $sql_genel_istatistikler, $genel_params, $genel_types);
$genel_istatistikler = [
    'toplam_hasta'    => 0,
    'ort_bmi'         => 0,
    'ort_glikoz'      => 0,
    'ort_gebelik'     => 0,
    'ort_kan_basinci' => 0
];
if ($result_genel && $result_genel->num_rows > 0) {
    $row = $result_genel->fetch_assoc();
    foreach ($row as $key => $value) {
        if (array_key_exists($key, $genel_istatistikler)) {
            $genel_istatistikler[$key] = $value ?? 0; // Null ise 0 ata
        }
    }
}

// -----------------------------------------------------------------------------
// 2. DİYABET RİSK SEGMENTASYONU (Pasta Grafik için)
// -----------------------------------------------------------------------------
$sql_risk_segmentasyon = "SELECT
                                risk_seviyesi,
                                COUNT(*) AS sayi
                            FROM (
                                SELECT
                                    {$sutun_yas},
                                    {$sutun_glikoz},
                                    {$sutun_bmi},
                                    {$risk_segment_sql} -- Risk seviyesini hesaplayan CASE ifadesi
                                FROM {$tablo_adi}
                                {$sql_genel_where_condition}
                            ) AS alt_risk_segmentasyonu -- Alt sorgu için takma ad
                            GROUP BY risk_seviyesi";

$result_risk = fetchData($conn, $sql_risk_segmentasyon, $genel_params, $genel_types);
$risk_data = ['Dusuk' => 0, 'Orta' => 0, 'Yuksek' => 0]; // Başlangıç değerleri
if ($result_risk) {
    while ($row = $result_risk->fetch_assoc()) {
        if (isset($risk_data[$row['risk_seviyesi']])) {
            $risk_data[$row['risk_seviyesi']] = (int)$row['sayi'];
        }
    }
}

// -----------------------------------------------------------------------------
// 3. YÜKSEK RİSKLİ HASTA YÜZDESİ (Gauge Grafik için)
// -----------------------------------------------------------------------------
$toplam_hasta_for_risk_calc = $genel_istatistikler['toplam_hasta'];
$yuksek_risk_hasta_sayisi   = $risk_data['Yuksek'] ?? 0;
$yuksek_risk_yuzde = ($toplam_hasta_for_risk_calc > 0)
    ? round(($yuksek_risk_hasta_sayisi / $toplam_hasta_for_risk_calc) * 100, 2)
    : 0;

// -----------------------------------------------------------------------------
// 4. YAŞA GÖRE ORTALAMA GLİKOZ DAĞILIMI (Çizgi Grafik için)
// -----------------------------------------------------------------------------
$sql_glikoz_yas = "SELECT
                        {$sutun_yas} AS yas,
                        ROUND(AVG(NULLIF({$sutun_glikoz}, 0)), 2) AS ort_glikoz
                    FROM {$tablo_adi}
                    {$sql_genel_where_condition}
                    GROUP BY yas
                    ORDER BY yas ASC"; // Yaşa göre sırala

$result_glikoz_yas = fetchData($conn, $sql_glikoz_yas, $genel_params, $genel_types);
$glikoz_yas_labels = [];
$glikoz_yas_values = [];
if ($result_glikoz_yas) {
    while ($row = $result_glikoz_yas->fetch_assoc()) {
        $glikoz_yas_labels[] = $row['yas'];
        $glikoz_yas_values[] = $row['ort_glikoz'] ?? 0;
    }
}

// -----------------------------------------------------------------------------
// 5. YAŞA GÖRE ORTALAMA KAN BASINCI DAĞILIMI (Çizgi Grafik için)
// -----------------------------------------------------------------------------
$sql_kanbasinci_yas = "SELECT
                            {$sutun_yas} AS yas,
                            ROUND(AVG(NULLIF({$sutun_kan_basinci}, 0)), 2) AS ort_kan_basinci
                        FROM {$tablo_adi}
                        {$sql_genel_where_condition}
                        GROUP BY yas
                        ORDER BY yas ASC"; // Yaşa göre sırala

$result_kanbasinci_yas = fetchData($conn, $sql_kanbasinci_yas, $genel_params, $genel_types);
$kanbasinci_yas_labels = [];
$kanbasinci_yas_values = [];
if ($result_kanbasinci_yas) {
    while ($row = $result_kanbasinci_yas->fetch_assoc()) {
        $kanbasinci_yas_labels[] = $row['yas'];
        $kanbasinci_yas_values[] = $row['ort_kan_basinci'] ?? 0;
    }
}

// -----------------------------------------------------------------------------
// 6. YAŞ GRUPLARINA GÖRE RİSK DAĞILIMI (Yığılmış Çubuk Grafik için)
// -----------------------------------------------------------------------------
$sql_yas_grup_risk = "SELECT
                            CASE
                                WHEN alt_yas_grup.{$sutun_yas} BETWEEN 20 AND 29 THEN '20-29'
                                WHEN alt_yas_grup.{$sutun_yas} BETWEEN 30 AND 39 THEN '30-39'
                                WHEN alt_yas_grup.{$sutun_yas} BETWEEN 40 AND 49 THEN '40-49'
                                WHEN alt_yas_grup.{$sutun_yas} BETWEEN 50 AND 59 THEN '50-59'
                                WHEN alt_yas_grup.{$sutun_yas} >= 60 THEN '60+'
                                ELSE 'Bilinmiyor'
                            END AS yas_grubu,
                            alt_yas_grup.risk_seviyesi,
                            COUNT(*) AS sayi
                        FROM (
                            SELECT
                                {$sutun_yas}, {$sutun_glikoz}, {$sutun_bmi},
                                {$risk_segment_sql}
                            FROM {$tablo_adi}
                            {$sql_genel_where_condition}
                        ) AS alt_yas_grup -- Alt sorgu için takma ad
                        GROUP BY yas_grubu, alt_yas_grup.risk_seviyesi
                        HAVING yas_grubu != 'Bilinmiyor' -- Bilinmeyen yaş gruplarını hariç tut
                        ORDER BY
                            yas_grubu ASC,
                            FIELD(alt_yas_grup.risk_seviyesi, 'Dusuk', 'Orta', 'Yuksek')"; // Risk seviyesine göre özel sıralama

$result_yas_grup_risk = fetchData($conn, $sql_yas_grup_risk, $genel_params, $genel_types);
$yas_gruplari_labels = ['20-29', '30-39', '40-49', '50-59', '60+']; // Grafik için etiketler
$yas_grup_risk_datasets_init = [
    'Dusuk'  => array_fill_keys($yas_gruplari_labels, 0),
    'Orta'   => array_fill_keys($yas_gruplari_labels, 0),
    'Yuksek' => array_fill_keys($yas_gruplari_labels, 0)
];
if ($result_yas_grup_risk) {
    while ($row = $result_yas_grup_risk->fetch_assoc()) {
        if (isset($yas_grup_risk_datasets_init[$row['risk_seviyesi']][$row['yas_grubu']])) {
            $yas_grup_risk_datasets_init[$row['risk_seviyesi']][$row['yas_grubu']] = (int)$row['sayi'];
        }
    }
}
$chart_yas_grup_risk_datasets = [];
foreach ($yas_grup_risk_datasets_init as $risk_seviyesi_key => $data_dizisi) {
    if (isset($risk_renkleri_map[$risk_seviyesi_key])) {
        $chart_yas_grup_risk_datasets[] = [
            'label'           => $risk_seviyesi_key,
            'data'            => array_values($data_dizisi), // Sadece değerleri al
            'backgroundColor' => $risk_renkleri_map[$risk_seviyesi_key],
            'borderColor'     => $risk_renkleri_map[$risk_seviyesi_key . 'Border'],
            'borderWidth'     => 1
        ];
    }
}

// -----------------------------------------------------------------------------
// 7. BMI KATEGORİLERİNE GÖRE ORTALAMA GLİKOZ (Çubuk Grafik için)
// -----------------------------------------------------------------------------
$sql_bmi_glikoz = "SELECT
                        CASE
                            WHEN {$sutun_bmi} < 18.5 THEN 'Zayif (<18.5)'
                            WHEN {$sutun_bmi} >= 18.5 AND {$sutun_bmi} < 25 THEN 'Normal (18.5-24.9)'
                            WHEN {$sutun_bmi} >= 25 AND {$sutun_bmi} < 30 THEN 'Fazla Kilolu (25-29.9)'
                            WHEN {$sutun_bmi} >= 30 THEN 'Obez (30+)'
                            ELSE 'Bilinmiyor'
                        END AS bmi_kategorisi,
                        ROUND(AVG(NULLIF({$sutun_glikoz}, 0)), 2) AS ort_glikoz
                    FROM {$tablo_adi}
                    {$sql_genel_where_condition}
                    GROUP BY bmi_kategorisi
                    HAVING bmi_kategorisi != 'Bilinmiyor'
                    ORDER BY FIELD(bmi_kategorisi, 'Zayif (<18.5)', 'Normal (18.5-24.9)', 'Fazla Kilolu (25-29.9)', 'Obez (30+)')";

$result_bmi_glikoz = fetchData($conn, $sql_bmi_glikoz, $genel_params, $genel_types);
$bmi_glikoz_labels = [];
$bmi_glikoz_values = [];
if ($result_bmi_glikoz) {
    while ($row = $result_bmi_glikoz->fetch_assoc()) {
        $bmi_glikoz_labels[] = $row['bmi_kategorisi'];
        $bmi_glikoz_values[] = (float)($row['ort_glikoz'] ?? 0);
    }
}

// -----------------------------------------------------------------------------
// 8. GEBELİK SAYISI VE DİYABET RİSKİ (Yığılmış Çubuk Grafik için)
// -----------------------------------------------------------------------------
$sql_gebelik_risk = "SELECT
                            alt_gebelik.{$sutun_gebelik}, -- Alt sorgudan gebelik sayısını al
                            alt_gebelik.risk_seviyesi,
                            COUNT(*) AS sayi
                        FROM (
                            SELECT
                                {$sutun_gebelik}, {$sutun_glikoz}, {$sutun_bmi}, {$sutun_yas},
                                {$risk_segment_sql}
                            FROM {$tablo_adi}
                            {$sql_genel_where_condition}
                        ) AS alt_gebelik -- Alt sorgu için takma ad
                        GROUP BY alt_gebelik.{$sutun_gebelik}, alt_gebelik.risk_seviyesi
                        ORDER BY
                            alt_gebelik.{$sutun_gebelik} ASC,
                            FIELD(alt_gebelik.risk_seviyesi, 'Dusuk', 'Orta', 'Yuksek')";

$result_gebelik_risk = fetchData($conn, $sql_gebelik_risk, $genel_params, $genel_types);
$gebelik_risk_labels_temp = []; // Benzersiz gebelik sayılarını geçici olarak tutar
$gebelik_risk_data_temp = [];   // Gebelik sayısı ve risk seviyesine göre hasta sayılarını tutar
if ($result_gebelik_risk) {
    while ($row = $result_gebelik_risk->fetch_assoc()) {
        $gebelik_sayisi = (int)$row[$sutun_gebelik]; // Dinamik sütun adı
        $risk_seviyesi  = $row['risk_seviyesi'];
        $hasta_sayisi   = (int)$row['sayi'];

        if (!in_array($gebelik_sayisi, $gebelik_risk_labels_temp, true)) {
            $gebelik_risk_labels_temp[] = $gebelik_sayisi;
        }
        $gebelik_risk_data_temp[$gebelik_sayisi][$risk_seviyesi] = $hasta_sayisi;
    }
}
sort($gebelik_risk_labels_temp); // Gebelik sayılarını sırala
$gebelik_risk_labels = array_map('strval', $gebelik_risk_labels_temp); // String'e çevir (Chart.js etiketleri için)

$gebelik_risk_datasets_init = ['Dusuk' => [], 'Orta' => [], 'Yuksek' => []];
foreach ($gebelik_risk_labels as $label_gebelik_sayisi_str) {
    $int_gebelik_sayisi = (int)$label_gebelik_sayisi_str;
    foreach (['Dusuk', 'Orta', 'Yuksek'] as $risk_anahtari) {
        $gebelik_risk_datasets_init[$risk_anahtari][] = $gebelik_risk_data_temp[$int_gebelik_sayisi][$risk_anahtari] ?? 0;
    }
}
$chart_gebelik_risk_datasets = [];
foreach (['Dusuk', 'Orta', 'Yuksek'] as $risk_seviyesi_anahtari) {
    if (isset($risk_renkleri_map[$risk_seviyesi_anahtari])) {
        $chart_gebelik_risk_datasets[] = [
            'label'           => $risk_seviyesi_anahtari,
            'data'            => $gebelik_risk_datasets_init[$risk_seviyesi_anahtari],
            'backgroundColor' => $risk_renkleri_map[$risk_seviyesi_anahtari],
            'borderColor'     => $risk_renkleri_map[$risk_seviyesi_anahtari . 'Border'],
            'borderWidth'     => 1
        ];
    }
}

// -----------------------------------------------------------------------------
// 9. DERİ KALINLIĞI vs İNSÜLİN DAĞILIMI (Scatter Plot için)
// -----------------------------------------------------------------------------
$base_scatter_where_parts = [];
// Sadece geçerli (NULL olmayan ve 0'dan büyük) değerleri al
if (!empty($sutun_deri_kalinligi)) {
    $base_scatter_where_parts[] = "{$sutun_deri_kalinligi} IS NOT NULL AND {$sutun_deri_kalinligi} > 0";
}
if (!empty($sutun_insulin)) {
    $base_scatter_where_parts[] = "{$sutun_insulin} IS NOT NULL AND {$sutun_insulin} > 0";
}

$scatter_final_where = "";
$combined_scatter_clauses = $base_scatter_where_parts;

// Genel filtreler varsa scatter plot'a da uygula
if (!empty($sql_genel_where_condition)) {
    // " WHERE " kısmını kaldırarak sadece koşulları al
    $filter_conditions_only = substr($sql_genel_where_condition, strlen(" WHERE "));
    if (!empty($filter_conditions_only)) {
        $combined_scatter_clauses[] = "({$filter_conditions_only})"; // Parantez içine alarak öncelik sağla
    }
}

if (!empty($combined_scatter_clauses)) {
    $scatter_final_where = " WHERE " . implode(" AND ", $combined_scatter_clauses);
}

$sql_deri_insulin = "SELECT
                        {$sutun_deri_kalinligi} AS deri,
                        {$sutun_insulin} AS insulin
                    FROM {$tablo_adi}
                    {$scatter_final_where}
                    LIMIT 300"; // Performans için sonuçları sınırla

// Scatter plot için genel filtre parametreleri ve tipleri kullanılabilir, çünkü $combined_scatter_clauses genel filtreleri içeriyor.
$result_deri_insulin = fetchData($conn, $sql_deri_insulin, $genel_params, $genel_types);
$deri_insulin_data = [];
if ($result_deri_insulin) {
    while ($row = $result_deri_insulin->fetch_assoc()) {
        $deri_insulin_data[] = [
            'x' => (float)($row['deri'] ?? 0),
            'y' => (float)($row['insulin'] ?? 0)
        ];
    }
}

// ... (PHP'nin geri kalanı: Dinamik Tablo Veri Çekme, HTML, CSS, JS) ...
?>

<?php
// ---- VERİ ÇEKME (DİNAMİK ARALIKLI TABLO İÇİN) ----
// Not: Bu sorgu BİRLEŞTİRİLMİŞ FİLTRELERİ ($sql_dinamik_tablo_where_condition, $dinamik_tablo_final_params, $dinamik_tablo_final_types) kullanır.
$dinamik_tablo_sonuclari = [];
// Sadece en az bir özel tablo filtresi girilmişse sorguyu çalıştır (veya her zaman çalıştırabilirsiniz)
$run_dinamik_tablo_sorgusu = !empty($filter_min_yas_tablo) || !empty($filter_max_yas_tablo) ||
                             !empty($filter_min_glikoz_tablo) || !empty($filter_max_glikoz_tablo) ||
                             !empty($filter_min_bmi_tablo) || !empty($filter_max_bmi_tablo);

if ($run_dinamik_tablo_sorgusu || !empty($sql_genel_where_condition) ) { // En az bir genel veya özel filtre varsa
    $sql_dinamik_tablo_verileri = "SELECT
                                    $sutun_id,
                                    $sutun_yas,
                                    $sutun_glikoz,
                                    $sutun_bmi,
                                    $sutun_kan_basinci,
                                    $sutun_gebelik,
                                    $risk_segment_sql
                                FROM $tablo_adi
                                $sql_dinamik_tablo_where_condition
                                ORDER BY $sutun_id ASC
                                LIMIT 100"; // Çok fazla sonuç gelmemesi için limit

    $result_dinamik_tablo = fetchData($conn, $sql_dinamik_tablo_verileri, $dinamik_tablo_final_params, $dinamik_tablo_final_types);
    if ($result_dinamik_tablo && $result_dinamik_tablo->num_rows > 0) {
        while ($row = $result_dinamik_tablo->fetch_assoc()) {
            $dinamik_tablo_sonuclari[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gelişmiş Diyabet Analiz Paneli</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        body { padding: 20px; background-color: #f8f9fa; color: #212529; transition: background-color 0.3s, color 0.3s; }
        .card { margin-bottom: 20px; box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075); transition: background-color 0.3s, border-color 0.3s; }
        .card-header { font-weight: bold; transition: background-color 0.3s, border-color 0.3s, color 0.3s;}
        h1, h3 { color: #343a40; transition: color 0.3s; }
        hr { margin-top: 1.5rem; margin-bottom: 1.5rem; transition: border-color 0.3s; }
        .chart-container { padding: 20px; background-color: #fff; border-radius: .3rem; margin-bottom:20px; height: 400px; transition: background-color 0.3s; }
        .gauge-container { display: flex; justify-content: center; align-items: center; height: 200px; }
        #riskGaugeText { text-align:center; font-size: 24px; font-weight: bold; position: absolute; width: 100%; top: 60%; transform: translateY(-50%); transition: color 0.3s;}
        .form-control-sm { height: calc(1.5em + .5rem + 2px); padding: .25rem .5rem; font-size: .875rem; line-height: 1.5; border-radius: .2rem; }
        .table-responsive { margin-top: 20px; }
        .form-control {transition: background-color 0.3s, color 0.3s, border-color 0.3s;}

        /* Dark Mode Styles */
        body.dark-mode {
            background-color: #1a1a1b; /* Slightly different dark */
            color: #e0e0e0;
        }
        body.dark-mode .card {
            background-color: #272728; /* Darker card */
            border-color: #404040;
            box-shadow: 0 0.125rem 0.25rem rgba(255,255,255,.05);
        }
        body.dark-mode .card-header {
            background-color: #333334; /* Darker header */
            border-bottom-color: #404040;
            color: #f0f0f0;
        }
        body.dark-mode h1, body.dark-mode h3, body.dark-mode .card-title {
            color: #f5f5f5;
        }
        body.dark-mode hr {
            border-top-color: #404040;
        }
        body.dark-mode .chart-container {
            background-color: #272728;
        }
        body.dark-mode #riskGaugeText {
             color: #f0f0f0;
        }
        body.dark-mode .form-control {
            background-color: #333334;
            color: #e0e0e0;
            border-color: #555;
        }
        body.dark-mode .form-control::placeholder {
            color: #888;
        }
        body.dark-mode .form-control:focus {
            background-color: #404040;
            color: #e0e0e0;
            border-color: #666;
            box-shadow: 0 0 0 0.2rem rgba(120,120,120,.25);
        }
        body.dark-mode select.form-control option {
            background-color: #333334;
            color: #e0e0e0;
        }

        body.dark-mode .table { color: #e0e0e0; }
        body.dark-mode .table th, body.dark-mode .table td { border-color: #404040; }
        body.dark-mode .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(255, 255, 255, 0.04); }
        body.dark-mode .table-hover tbody tr:hover { background-color: rgba(255, 255, 255, 0.07); }
        body.dark-mode .thead-light th {
            color: #f0f0f0;
            background-color: #333334;
            border-color: #404040;
        }
        body.dark-mode .btn-secondary { background-color: #4a4a4b; border-color: #5a5a5b; color: #e0e0e0; }
        body.dark-mode .btn-secondary:hover { background-color: #5a5a5b; border-color: #6a6a6b; color: #fff; }
        body.dark-mode .btn-outline-secondary { border-color: #5a5a5b; color: #ccc; }
        body.dark-mode .btn-outline-secondary:hover { background-color: #4a4a4b; color: #fff; }
        
        body.dark-mode .text-muted { color: #999 !important; }
        body.dark-mode .chart-container p.text-muted { color: #888 !important; }
        body.dark-mode .text-info { color: #28a5bc !important; } /* Lighter info for dark mode */
        
        /* Specific card background colors in dark mode - if needed */
        /* body.dark-mode .bg-primary { background-color: #0056b3 !important; } */

        #darkModeToggle {
            position: fixed; top: 15px; right: 15px; z-index: 1050;
            border: 1px solid #ccc; background-color: #fff; color: #333;
            padding: 0.3rem 0.6rem; font-size: 1rem;
        }
        body.dark-mode #darkModeToggle {
            border: 1px solid #555; background-color: #333; color: #fff;
        }
    </style>
</head>
<body>
    <button id="darkModeToggle" class="btn btn-sm">🌙</button>

    <div class="container-fluid">
        <h1 class="mb-4">Uluslararası FuAy Hastanesi - Gelişmiş Diyabet Analiz Paneli</h1>
        <hr>

        <!-- GENEL FİLTRELEME FORMU -->
        <div class="card mb-4">
            <div class="card-header">Genel Filtreler (Grafikler ve Genel Bakış İçin)</div>
            <div class="card-body">
                <form id="genelFilterForm" method="GET" action="">
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="filterYas">Yaş (Örn: 30-50, >30, <50, 40):</label>
                            <input type="text" class="form-control" name="filterYas" id="filterYas" value="<?php echo htmlspecialchars($_GET['filterYas'] ?? ''); ?>" />
                        </div>
                        <div class="form-group col-md-4">
                            <label for="filterRisk">Risk Seviyesi:</label>
                            <select name="filterRisk" id="filterRisk" class="form-control">
                                <option value="">Tümü</option>
                                <?php foreach (['Yuksek', 'Orta', 'Dusuk'] as $risk_val): ?>
                                <option value="<?php echo $risk_val; ?>" <?php echo (($filter_risk_seviyesi_genel ?? '') == $risk_val ? 'selected' : ''); ?>><?php echo ucfirst(strtolower($risk_val)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-4 align-self-end">
                            <button type="submit" class="btn btn-primary mr-2">Genel Filtrele</button>
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"],'?'); ?>" class="btn btn-secondary">Tümünü Sıfırla</a>
                        </div>
                    </div>
                    <!-- Dinamik tablo için GET parametrelerini gizli inputlarla taşı, böylece genel filtreleme formu gönderildiğinde kaybolmazlar -->
                    <input type="hidden" name="filterMinYasTablo" value="<?php echo htmlspecialchars($filter_min_yas_tablo); ?>">
                    <input type="hidden" name="filterMaxYasTablo" value="<?php echo htmlspecialchars($filter_max_yas_tablo); ?>">
                    <input type="hidden" name="filterMinGlikozTablo" value="<?php echo htmlspecialchars($filter_min_glikoz_tablo); ?>">
                    <input type="hidden" name="filterMaxGlikozTablo" value="<?php echo htmlspecialchars($filter_max_glikoz_tablo); ?>">
                    <input type="hidden" name="filterMinBmiTablo" value="<?php echo htmlspecialchars($filter_min_bmi_tablo); ?>">
                    <input type="hidden" name="filterMaxBmiTablo" value="<?php echo htmlspecialchars($filter_max_bmi_tablo); ?>">
                </form>
            </div>
        </div>
        <hr>

        <!-- 1. Genel İstatistik Kartları -->
        <h3>Genel İstatistikler</h3>
        <div class="row mb-4">
            <?php
            $kartlar = [
                ['Toplam Hasta', 'primary', 'toplam_hasta'], ['Ort. BMI', 'info', 'ort_bmi'],
                ['Ort. Glikoz', 'success', 'ort_glikoz'], ['Ort. Gebelik', 'secondary', 'ort_gebelik'],
                ['Ort. Kan Basıncı', 'warning', 'ort_kan_basinci']
            ];
            foreach ($kartlar as $k) {
                echo "<div class='col-lg col-md-4 col-sm-6 mb-3'>
                        <div class='card text-white bg-{$k[1]} h-100'>
                            <div class='card-header'>{$k[0]}</div>
                            <div class='card-body d-flex align-items-center justify-content-center'>
                                <h3 class='card-title mb-0'>" . ($genel_istatistikler[$k[2]] ?? 'N/A') . "</h3>
                            </div>
                        </div>
                    </div>";
            }
            ?>
        </div>
        <hr>

        <!-- Grafik Alanları -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="chart-container">
                    <h3> Risk Segmentasyonu</h3>
                    <canvas id="riskSegmentasyonChart"></canvas>
                </div>
            </div>  
            <div class="col-md-4 mb-4">
                 <div class="chart-container gauge-container">
                    <div style="width: 220px; height: 110px; position: relative;">
                        <canvas id="riskGaugeChart"></canvas>
                        <div id="riskGaugeText"></div>
                    </div>
                </div>
                <h3 class="text-center mt-2"> Yüksek Risk Yüzdesi</h3>
            </div>
             <div class="col-md-4 mb-4">
                <div class="chart-container">
                    <h3> BMI ve Ort. Glikoz</h3>
                    <canvas id="bmiGlikozChart"></canvas>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                 <div class="chart-container">
                    <h3> Glikoz ve Yaş</h3>
                    <canvas id="glikozYasChart"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                 <div class="chart-container">
                    <h3>Kan Basıncı ve Yaş</h3>
                    <canvas id="kanBasinciYasChart"></canvas>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="chart-container">
                    <h3>Yaş Grupları ve Risk</h3>
                    <canvas id="yasGrupRiskChart"></canvas>
                </div>
            </div>
             <div class="col-md-6 mb-4">
                <div class="chart-container">
                    <h3> Gebelik Sayısı ve Risk</h3>
                    <canvas id="gebelikRiskChart"></canvas>
                </div>
            </div>
        </div>

         <div class="row">
            <div class="col-md-6 mb-4">
                <div class="chart-container">
                    <h3>Deri K. ve İnsülin</h3>
                    <canvas id="deriInsulinChart"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                 <div class="chart-container d-flex justify-content-center align-items-center">
                    <p class="text-muted">Gelecek Grafik Alanı</p>
                </div>
            </div>
        </div>
        <hr>

        <!-- DİNAMİK ARALIKLI VERİ LİSTELEME TABLOSU -->
         <div id="dinamikTabloAlani">  </div>
        <div class="card mt-5">
            <div class="card-header">Detaylı Hasta Listesi (Aralık Filtreleri İle)</div>
            <div class="card-body">
                <form id="dinamikTabloFilterForm" method="GET" action="">
                    <!-- Genel filtreleri gizli input olarak taşıyalım ki bu form gönderildiğinde kaybolmasınlar -->
                    <input type="hidden" name="filterYas" value="<?php echo htmlspecialchars($filter_yas_str_genel); ?>">
                    <input type="hidden" name="filterRisk" value="<?php echo htmlspecialchars($filter_risk_seviyesi_genel); ?>">

                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2">
                            <label for="filterMinYasTablo">Min Yaş:</label>
                            <input type="number" class="form-control form-control-sm" name="filterMinYasTablo" id="filterMinYasTablo" value="<?php echo htmlspecialchars($filter_min_yas_tablo); ?>" placeholder="Min">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="filterMaxYasTablo">Max Yaş:</label>
                            <input type="number" class="form-control form-control-sm" name="filterMaxYasTablo" id="filterMaxYasTablo" value="<?php echo htmlspecialchars($filter_max_yas_tablo); ?>" placeholder="Max">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="filterMinGlikozTablo">Min Glikoz:</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" name="filterMinGlikozTablo" id="filterMinGlikozTablo" value="<?php echo htmlspecialchars($filter_min_glikoz_tablo); ?>" placeholder="Min">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="filterMaxGlikozTablo">Max Glikoz:</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" name="filterMaxGlikozTablo" id="filterMaxGlikozTablo" value="<?php echo htmlspecialchars($filter_max_glikoz_tablo); ?>" placeholder="Max">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="filterMinBmiTablo">Min BMI:</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" name="filterMinBmiTablo" id="filterMinBmiTablo" value="<?php echo htmlspecialchars($filter_min_bmi_tablo); ?>" placeholder="Min">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="filterMaxBmiTablo">Max BMI:</label>
                            <input type="number" step="0.1" class="form-control form-control-sm" name="filterMaxBmiTablo" id="filterMaxBmiTablo" value="<?php echo htmlspecialchars($filter_max_bmi_tablo); ?>" placeholder="Max">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-12 text-right">
                            <button type="submit" class="btn btn-info btn-sm mr-2">Listeyi Filtrele</button>
                            <a href="<?php echo strtok($_SERVER["REQUEST_URI"],'?') . '?filterYas=' . urlencode($filter_yas_str_genel) . '&filterRisk=' . urlencode($filter_risk_seviyesi_genel);?>" class="btn btn-outline-secondary btn-sm">Liste Filtrelerini Sıfırla</a>
                        </div>
                    </div>
                </form>

                <?php if (!empty($dinamik_tablo_sonuclari)): ?>
                    <p class="text-muted mt-2"><?php echo count($dinamik_tablo_sonuclari); ?> kayıt bulundu (En fazla 100 kayıt gösterilir).</p>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-hover table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Yaş</th>
                                    <th>Glikoz</th>
                                    <th>BMI (VKİ)</th>
                                    <th>Kan Basıncı</th>
                                    <th>Gebelik S.</th>
                                    <th>Risk Seviyesi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dinamik_tablo_sonuclari as $hasta): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($hasta[$sutun_id] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($hasta[$sutun_yas] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($hasta[$sutun_glikoz] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($hasta[$sutun_bmi] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($hasta[$sutun_kan_basinci] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($hasta[$sutun_gebelik] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        $risk_etiket = htmlspecialchars($hasta['risk_seviyesi'] ?? 'N/A');
                                        $risk_renk_class = '';
                                        if ($risk_etiket === 'Yuksek') $risk_renk_class = 'text-danger font-weight-bold';
                                        elseif ($risk_etiket === 'Orta') $risk_renk_class = 'text-warning font-weight-bold';
                                        elseif ($risk_etiket === 'Dusuk') $risk_renk_class = 'text-success';
                                        echo "<span class='$risk_renk_class'>$risk_etiket</span>";
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($run_dinamik_tablo_sorgusu || !empty($sql_genel_where_condition)): ?>
                    <p class="text-info mt-3">Belirtilen kriterlere uygun hasta kaydı bulunamadı.</p>
                <?php else: ?>
                     <p class="text-muted mt-3">Lütfen yukarıdaki genel filtrelerden veya liste filtrelerinden birini kullanarak arama yapınız.</p>
                <?php endif; ?>
            </div>
        </div>
        <hr class="mt-5">
    </div>

<script>
    const chartInstances = {};
    const chartConfigGenerators = {};

    // Helper for deep merging options
    function deepMerge(target, source) {
        for (const key in source) {
            if (source.hasOwnProperty(key)) {
                if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key]) && target[key] && typeof target[key] === 'object') {
                    deepMerge(target[key], source[key]);
                } else {
                    target[key] = source[key];
                }
            }
        }
        return target;
    }

    const getThemeAwareChartOptions = (titleText, specificOptions = {}) => {
        const isDarkMode = document.body.classList.contains('dark-mode');
        const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.1)';
        const tickColor = isDarkMode ? '#adb5bd' : '#495057';
        const titleFontColor = isDarkMode ? '#f8f9fa' : '#343a40';
        const legendLabelColor = isDarkMode ? '#f8f9fa' : '#343a40';

        let baseOptions = {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 15, color: legendLabelColor } },
                title: { display: !!titleText, text: titleText, font: { size: 16 }, color: titleFontColor, padding: { top: 10, bottom: 10 } },
                tooltip: {
                    backgroundColor: isDarkMode ? 'rgba(40,40,40,0.9)' : 'rgba(255,255,255,0.9)',
                    titleColor: isDarkMode ? '#f0f0f0' : '#333',
                    bodyColor: isDarkMode ? '#f0f0f0' : '#333',
                    borderColor: isDarkMode ? '#555' : '#ddd',
                    borderWidth: 1
                }
            },
            scales: {
                x: { grid: { display: false, drawBorder: true, borderColor: gridColor }, ticks: { color: tickColor }, title: { display: false, text: '', color: titleFontColor } },
                y: { grid: { color: gridColor, drawBorder: true, borderColor: gridColor }, ticks: { color: tickColor }, title: { display: false, text: '', color: titleFontColor } }
            }
        };
        
        // Apply specific scale titles if provided
        if (specificOptions.scales?.x?.title?.text) { baseOptions.scales.x.title.display = true; baseOptions.scales.x.title.text = specificOptions.scales.x.title.text; }
        if (specificOptions.scales?.y?.title?.text) { baseOptions.scales.y.title.display = true; baseOptions.scales.y.title.text = specificOptions.scales.y.title.text; }
        
        // Merge other specific options deeply
        return deepMerge(JSON.parse(JSON.stringify(baseOptions)), specificOptions); // Use stringify/parse for a deep clone before merge
    };

    const jsRiskColors = { 'Dusuk': '<?php echo $risk_renkleri_map['Dusuk']; ?>', 'Orta': '<?php echo $risk_renkleri_map['Orta']; ?>', 'Yuksek': '<?php echo $risk_renkleri_map['Yuksek']; ?>' };
    const jsRiskBorderColors = { 'Dusuk': '<?php echo $risk_renkleri_map['DusukBorder']; ?>', 'Orta': '<?php echo $risk_renkleri_map['OrtaBorder']; ?>', 'Yuksek': '<?php echo $risk_renkleri_map['YuksekBorder']; ?>' };

    function initializeOrUpdateCharts() {
        const riskGaugePercent = <?php echo $yuksek_risk_yuzde; ?>;
        if(document.getElementById('riskGaugeText')) document.getElementById('riskGaugeText').innerText = riskGaugePercent + '%';

        const chartDefinitions = {
            riskSegmentasyonChart: {
                id: 'riskSegmentasyonChart',
                generator: () => ({
                    type: 'doughnut',
                    data: { labels: ['Düşük', 'Orta', 'Yüksek'], datasets: [{ data: [<?php echo $risk_data['Dusuk'] ?? 0; ?>, <?php echo $risk_data['Orta'] ?? 0; ?>, <?php echo $risk_data['Yuksek'] ?? 0; ?>], backgroundColor: [jsRiskColors.Dusuk, jsRiskColors.Orta, jsRiskColors.Yuksek], borderColor: [jsRiskBorderColors.Dusuk, jsRiskBorderColors.Orta, jsRiskBorderColors.Yuksek], borderWidth: 1 }] },
                    options: getThemeAwareChartOptions('Diyabet Risk Segmentasyonu')
                })
            },
            riskGaugeChart: {
                id: 'riskGaugeChart',
                generator: () => {
                    const isDarkMode = document.body.classList.contains('dark-mode');
                    return {
                        type: 'doughnut',
                        data: { datasets: [{ data: [riskGaugePercent, 100 - riskGaugePercent], backgroundColor: [jsRiskColors.Yuksek, isDarkMode ? 'rgba(80, 80, 80, 0.6)' : 'rgba(220,220,220,0.4)'], borderWidth: 0 }] },
                        options: getThemeAwareChartOptions(null, { circumference: 180, rotation: 270, cutout: '70%', plugins: { legend: { display: false }, tooltip: { enabled: false } } })
                    };
                }
            },
            glikozYasChart: {
                id: 'glikozYasChart',
                generator: () => ({
                    type: 'line', data: { labels: <?php echo json_encode($glikoz_yas_labels); ?>, datasets: [{ label: 'Ort. Glikoz', data: <?php echo json_encode($glikoz_yas_values); ?>, borderColor: '#4BC0C0', backgroundColor: 'rgba(75,192,192,0.2)', fill: true, tension: 0.2 }] },
                    options: getThemeAwareChartOptions('Yaşa Göre Ortalama Glikoz', { scales: { x: { title: { text: 'Yaş' } }, y: { title: { text: 'Ort. Glikoz' }, beginAtZero: false } } })
                })
            },
            kanBasinciYasChart: {
                id: 'kanBasinciYasChart',
                generator: () => ({
                    type: 'line', data: { labels: <?php echo json_encode($kanbasinci_yas_labels); ?>, datasets: [{ label: 'Ort. Kan Basıncı', data: <?php echo json_encode($kanbasinci_yas_values); ?>, borderColor: '#FF9F40', backgroundColor: 'rgba(255,159,64,0.2)', fill: true, tension: 0.2 }] },
                    options: getThemeAwareChartOptions('Yaşa Göre Ortalama Kan Basıncı', { scales: { x: { title: { text: 'Yaş' } }, y: { title: { text: 'Ort. Kan Basıncı' }, beginAtZero: false } } })
                })
            },
            yasGrupRiskChart: {
                id: 'yasGrupRiskChart',
                generator: () => ({
                    type: 'bar', data: { labels: <?php echo json_encode($yas_gruplari_labels); ?>, datasets: <?php echo json_encode($chart_yas_grup_risk_datasets); ?> },
                    options: getThemeAwareChartOptions('Yaş Gruplarına Göre Risk Dağılımı', { scales: { x: { stacked: true, title: { text: 'Yaş Grubu' } }, y: { stacked: true, title: { text: 'Hasta Sayısı' } } } })
                })
            },
            bmiGlikozChart: {
                id: 'bmiGlikozChart',
                generator: () => ({
                    type: 'bar', data: { labels: <?php echo json_encode($bmi_glikoz_labels); ?>, datasets: [{ label: 'Ortalama Glikoz', data: <?php echo json_encode($bmi_glikoz_values); ?>, backgroundColor: ['#A076F9', '#75C2F6', '#F4D160', '#FF6D60'], borderColor: ['#6941C6', '#1675C9', '#B5912D', '#C33C2D'], borderWidth: 1 }] },
                    options: getThemeAwareChartOptions('BMI Kategorisi vs Ortalama Glikoz', { scales: { y: { title: { text: 'Ortalama Glikoz' }, beginAtZero: false } } })
                })
            },
            gebelikRiskChart: {
                id: 'gebelikRiskChart',
                generator: () => ({
                    type: 'bar', data: { labels: <?php echo json_encode($gebelik_risk_labels); ?>, datasets: <?php echo json_encode($chart_gebelik_risk_datasets); ?> },
                    options: getThemeAwareChartOptions('Gebelik Sayısı ve Diyabet Riski', { scales: { x: { title: { text: 'Gebelik Sayısı' } }, y: { title: { text: 'Hasta Sayısı' } } } })
                })
            },
            deriInsulinChart: {
                id: 'deriInsulinChart',
                generator: () => ({
                    type: 'scatter', data: { datasets: [{ label: 'Hasta Verileri', data: <?php echo json_encode($deri_insulin_data); ?>, backgroundColor: 'rgba(255, 99, 132, 0.6)', borderColor: 'rgba(255,99,132,1)', pointRadius: 4, pointHoverRadius: 6 }] },
                    options: getThemeAwareChartOptions('Deri Kalınlığı vs İnsülin', { scales: { x: { type: 'linear', position: 'bottom', title: { text: 'Deri Kalınlığı (mm)' } }, y: { title: { text: 'İnsülin (mu U/ml)' } } } })
                })
            }
        };

        for (const key in chartDefinitions) {
            const def = chartDefinitions[key];
            const element = document.getElementById(def.id);
            if (element) {
                if (!chartInstances[def.id]) {
                    chartConfigGenerators[def.id] = def.generator;
                    chartInstances[def.id] = new Chart(element, chartConfigGenerators[def.id]());
                } else {
                    const newConfig = chartConfigGenerators[def.id]();
                    chartInstances[def.id].data = newConfig.data;
                    chartInstances[def.id].options = newConfig.options;
                    chartInstances[def.id].update();
                }
            }
        }
    }

    const darkModeToggle = document.getElementById('darkModeToggle');
    const preferDark = window.matchMedia('(prefers-color-scheme: dark)');

    function applyTheme(theme) {
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            darkModeToggle.innerHTML = '☀';
        } else {
            document.body.classList.remove('dark-mode');
            darkModeToggle.innerHTML = '🌙';
        }
        initializeOrUpdateCharts();
    }

    darkModeToggle.addEventListener('click', () => {
        let newTheme = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    });

    let currentTheme = localStorage.getItem('theme');
    if (!currentTheme) {
        currentTheme = preferDark.matches ? 'dark' : 'light';
    }
    applyTheme(currentTheme);

    preferDark.addEventListener('change', (e) => {
        if (!localStorage.getItem('theme')) {
            applyTheme(e.matches ? 'dark' : 'light');
        }
    });

</script>
<?php
if (isset($conn)) { $conn->close(); }
?>
</body>
</html>