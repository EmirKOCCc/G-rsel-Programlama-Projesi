// script.js
let riskSegmentasyonChartInstance = null;
let riskGaugeChartInstance = null;
let glikozYasChartInstance = null;
let kanBasinciYasChartInstance = null;
let korelasyonChartInstance = null;

// Renkler (Chart.js için)
const riskColors = {
    'Yuksek': 'rgba(255, 99, 132, 0.7)', // Kırmızı
    'Orta': 'rgba(255, 206, 86, 0.7)',  // Sarı
    'Dusuk': 'rgba(75, 192, 192, 0.7)'  // Yeşil
};
const riskBorderColors = {
    'Yuksek': 'rgba(255, 99, 132, 1)',
    'Orta': 'rgba(255, 206, 86, 1)',
    'Dusuk': 'rgba(75, 192, 192, 1)'
};

function updateDashboard(filterParams = '') {
    fetch(`data_provider.php?${filterParams}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.error_genel || data.error_risk_segment || data.error_glikoz_yas || data.error_kanb_yas || data.error_korelasyon) {
                console.error("Sunucudan hata alındı:", data);
                alert("Veri yüklenirken bir sorun oluştu. Lütfen konsolu kontrol edin.");
                return;
            }

            // 1. Genel İstatistikler
            $('#toplamHastaSayisi').text(data.genelIstatistikler.toplam_hasta || 0);
            $('#ortalamaBMI').text(data.genelIstatistikler.ort_bmi || 0);
            $('#ortalamaGlikoz').text(data.genelIstatistikler.ort_glikoz || 0);
            $('#ortalamaGebelik').text(data.genelIstatistikler.ort_gebelik || 0);
            $('#ortalamaKanBasinci').text(data.genelIstatistikler.ort_kan_basinci || 0);

            // 2. Diyabet Risk Segmentasyonu (Pasta Grafik)
            const ctxRiskSegmentasyon = document.getElementById('riskSegmentasyonChart').getContext('2d');
            if (riskSegmentasyonChartInstance) {
                riskSegmentasyonChartInstance.destroy();
            }
            riskSegmentasyonChartInstance = new Chart(ctxRiskSegmentasyon, {
                type: 'pie',
                data: {
                    labels: data.riskSegmentasyonu.labels || ['Veri Yok'],
                    datasets: [{
                        label: 'Hasta Sayısı',
                        data: data.riskSegmentasyonu.data && data.riskSegmentasyonu.data.length > 0 ? data.riskSegmentasyonu.data : [1],
                        backgroundColor: data.riskSegmentasyonu.labels ? data.riskSegmentasyonu.labels.map(label => riskColors[label] || 'rgba(200, 200, 200, 0.7)') : ['rgba(200, 200, 200, 0.7)'],
                        borderColor: data.riskSegmentasyonu.labels ? data.riskSegmentasyonu.labels.map(label => riskBorderColors[label] || 'rgba(200, 200, 200, 1)') : ['rgba(200, 200, 200, 1)'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        title: { display: true, text: 'Risk Gruplarına Göre Hasta Dağılımı' }
                    }
                }
            });

            // 6. Risk Göstergesi (Gauge - Chart.js'de doğrudan yok, doughnut ile benzeri)
            const ctxRiskGauge = document.getElementById('riskGaugeChart').getContext('2d');
            const riskGaugeValue = data.riskGauge.value || 0;
            $('#riskGaugeText').text(`${riskGaugeValue}%`);

            if (riskGaugeChartInstance) {
                riskGaugeChartInstance.destroy();
            }
            riskGaugeChartInstance = new Chart(ctxRiskGauge, {
                type: 'doughnut',
                data: {
                    labels: ['Yüksek Risk', 'Diğer'],
                    datasets: [{
                        data: [riskGaugeValue, 100 - riskGaugeValue],
                        backgroundColor: [riskColors['Yuksek'], 'rgba(200, 200, 200, 0.3)'],
                        borderColor: [riskBorderColors['Yuksek'], 'rgba(200, 200, 200, 0.5)'],
                        borderWidth: 1,
                        circumference: 180, // Yarım daire
                        rotation: 270      // Başlangıç noktası
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false }, title: { display: false } },
                    cutout: '70%' // Ortadaki boşluk
                }
            });


            // 3. Glikoz - Yaş Trendi (Çizgi Grafik)
            const ctxGlikozYas = document.getElementById('glikozYasChart').getContext('2d');
            if (glikozYasChartInstance) {
                glikozYasChartInstance.destroy();
            }
            glikozYasChartInstance = new Chart(ctxGlikozYas, {
                type: 'line', // veya 'bar'
                data: {
                    labels: data.glikozYasTrendi.labels && data.glikozYasTrendi.labels.length > 0 ? data.glikozYasTrendi.labels : ['Veri Yok'],
                    datasets: [{
                        label: `Ortalama ${sutunlar.glikoz}`,
                        data: data.glikozYasTrendi.data && data.glikozYasTrendi.data.length > 0 ? data.glikozYasTrendi.data : [0],
                        borderColor: 'rgb(75, 192, 192)',
                        tension: 0.1
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: false, title: { display: true, text: `Ortalama ${sutunlar.glikoz}` } },
                              x: { title: { display: true, text: 'Yaş' } }
                    },
                    responsive: true
                }
            });

            // 3. Kan Basıncı - Yaş Trendi (Çizgi Grafik)
            const ctxKanBasinciYas = document.getElementById('kanBasinciYasChart').getContext('2d');
            if (kanBasinciYasChartInstance) {
                kanBasinciYasChartInstance.destroy();
            }
            kanBasinciYasChartInstance = new Chart(ctxKanBasinciYas, {
                type: 'line', // veya 'bar'
                data: {
                    labels: data.kanBasinciYasTrendi.labels && data.kanBasinciYasTrendi.labels.length > 0 ? data.kanBasinciYasTrendi.labels : ['Veri Yok'],
                    datasets: [{
                        label: `Ortalama ${sutunlar.kanBasinci}`,
                        data: data.kanBasinciYasTrendi.data && data.kanBasinciYasTrendi.data.length > 0 ? data.kanBasinciYasTrendi.data : [0],
                        borderColor: 'rgb(255, 159, 64)',
                        tension: 0.1
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: false, title: { display: true, text: `Ortalama ${sutunlar.kanBasinci}` } },
                              x: { title: { display: true, text: 'Yaş' } }
                    },
                    responsive: true
                }
            });

            // 4. Korelasyon Analizi (Scatter Plot)
            const ctxKorelasyon = document.getElementById('korelasyonChart').getContext('2d');
            if (korelasyonChartInstance) {
                korelasyonChartInstance.destroy();
            }
            // Korelasyon verisini risk gruplarına göre ayır
            const datasetsKorelasyon = [];
            const riskGruplari = [...new Set(data.korelasyonVerisi.map(item => item.risk))]; // Benzersiz risk grupları

            riskGruplari.forEach(riskGrubu => {
                datasetsKorelasyon.push({
                    label: riskGrubu,
                    data: data.korelasyonVerisi.filter(d => d.risk === riskGrubu).map(d => ({ x: d.x, y: d.y })),
                    backgroundColor: riskColors[riskGrubu] || 'rgba(150, 150, 150, 0.7)',
                    borderColor: riskBorderColors[riskGrubu] || 'rgba(150, 150, 150, 1)',
                    pointRadius: 5,
                    pointHoverRadius: 7
                });
            });

            if (data.korelasyonVerisi && data.korelasyonVerisi.length > 0) {
                korelasyonChartInstance = new Chart(ctxKorelasyon, {
                    type: 'scatter',
                    data: {
                        datasets: datasetsKorelasyon
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { position: 'top' },
                            title: { display: true, text: `${sutunlar.glikoz} vs ${sutunlar.bmi} Dağılımı (Risk Grubuna Göre)`}
                        },
                        scales: {
                            x: {
                                title: { display: true, text: sutunlar.glikoz },
                                // beginAtZero: true // Glikoz için 0'dan başlamak mantıklı olmayabilir
                            },
                            y: {
                                title: { display: true, text: sutunlar.bmi },
                                beginAtZero: true
                            }
                        }
                    }
                });
            } else {
                 // Veri yoksa canvas'ı temizle veya bir mesaj göster
                ctxKorelasyon.clearRect(0, 0, ctxKorelasyon.canvas.width, ctxKorelasyon.canvas.height);
                ctxKorelasyon.textAlign = 'center';
                ctxKorelasyon.fillText('Korelasyon için veri bulunamadı.', ctxKorelasyon.canvas.width / 2, ctxKorelasyon.canvas.height / 2);
            }


        })
        .catch(error => {
            console.error('Dashboard güncellenirken hata oluştu:', error);
            alert('Dashboard güncellenirken bir hata oluştu. Lütfen konsolu kontrol edin.');
        });
}

// Sayfa yüklendiğinde ilk verileri çek
document.addEventListener('DOMContentLoaded', function() {
    updateDashboard(); // Başlangıçta filtresiz yükle

    // Filtreleri Uygula Butonu
    document.getElementById('applyFilters').addEventListener('click', function() {
        const yas = document.getElementById('filterYas').value;
        // const cinsiyetElement = document.getElementById('filterCinsiyet'); // Cinsiyet varsa
        // const cinsiyet = cinsiyetElement ? cinsiyetElement.value : ''; // Cinsiyet varsa
        const risk = document.getElementById('filterRisk').value;

        let params = new URLSearchParams();
        if (yas) params.append('yas', yas);
        // if (cinsiyet) params.append('cinsiyet', cinsiyet); // Cinsiyet varsa
        if (risk) params.append('risk', risk);

        updateDashboard(params.toString());
    });

    // Filtreleri Sıfırla Butonu
    document.getElementById('resetFilters').addEventListener('click', function() {
        document.getElementById('filterForm').reset();
        updateDashboard(); // Filtresiz yükle
    });
});