<?php
require_once 'koneksi.php';
$query = "SELECT * FROM gejala ORDER BY kode_gejala ASC";
$result_gejala = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engine Diagnosa - EduExpert AI</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <nav class="navbar">
        <div class="logo">EDUTPAKAI.AI</div>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="diagnosa.php" class="active">Mulai Konsultasi</a>
        </div>
    </nav>

    <div class="main-container">
        <div class="card-glass">
            <h2 style="font-size: 2rem; font-weight: 800; margin-bottom: 8px;">Workspace Analisis</h2>
            <p style="color: var(--text-muted); margin-bottom: 30px;">Tandai semua indikasi yang terlihat pada siswa (bisa pilih sebanyak mungkin):</p>
            
            <form action="" method="POST">
                <div class="gejala-grid">
                    <?php while ($row = $result_gejala->fetch_assoc()): ?>
                        <?php 
                        // Mempertahankan status centang setelah submit agar user tahu apa yang mereka pilih
                        $checked = (isset($_POST['gejala']) && in_array($row['kode_gejala'], $_POST['gejala'])) ? 'checked' : '';
                        $selected_class = $checked ? 'selected' : '';
                        ?>
                        <div class="gejala-item <?= $selected_class; ?>" onclick="toggleCheckbox(this)">
                            <input type="checkbox" name="gejala[]" value="<?= $row['kode_gejala']; ?>" id="<?= $row['kode_gejala']; ?>" <?= $checked; ?> onclick="event.stopPropagation(); handleCheck(this);">
                            <label for="<?= $row['kode_gejala']; ?>" style="cursor:pointer; font-weight:500;">
                                <span style="color:var(--primary); font-weight:700; margin-right:5px;">[<?= $row['kode_gejala']; ?>]</span> <?= htmlspecialchars($row['nama_gejala']); ?>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>
                <button type="submit" name="proses" class="btn-gradient" style="width: 100%; border:none; cursor:pointer;">Eksekusi Mesin Inferensi Sekarang</button>
            </form>

            <?php
            if (isset($_POST['proses'])) {
                $working_memory = isset($_POST['gejala']) ? $_POST['gejala'] : [];
                echo '<div id="hasil-analisis" style="scroll-margin-top: 80px; margin-top: 40px;"></div>';

                if (count($working_memory) < 2) {
                    echo '<div style="border: 1px solid #ef4444; background: rgba(239, 68, 68, 0.05); padding: 30px; border-radius: 16px;">';
                    echo '<h3 style="color:#ef4444; margin-bottom:10px;">Error Kriteria Minimum</h3>';
                    echo '<p style="color:var(--text-muted);">Sistem membutuhkan minimal 2 gejala yang dipilih untuk menganalisis kecenderungan penyebab.</p>';
                    echo '</div>';
                } else {
                    $query_rules = "SELECT * FROM rules";
                    $res_rules = $conn->query($query_rules);
                    
                    $rule_scores = []; // Menampung skor kecocokan tiap rule

                    while ($rule = $res_rules->fetch_assoc()) {
                        $score = 0;
                        // Cek bobot kecocokan gejala 1
                        if (in_array($rule['gejala_1'], $working_memory)) {
                            $score++;
                        }
                        // Cek bobot kecocokan gejala 2
                        if (in_array($rule['gejala_2'], $working_memory)) {
                            $score++;
                        }

                        // Simpan skor jika ada gejala yang cocok
                        if ($score > 0) {
                            $rule_scores[] = [
                                'kode_rule' => $rule['kode_rule'],
                                'kode_penyebab' => $rule['kode_penyebab'],
                                'score' => $score,
                                'gejala_1' => $rule['gejala_1'],
                                'gejala_2' => $rule['gejala_2']
                            ];
                        }
                    }

                    // Urutkan skor dari yang paling tinggi (paling cocok dengan inputan user)
                    usort($rule_scores, function($a, $b) {
                        return $b['score'] <=> $a['score'];
                    });

                    // Ambil rule yang memiliki tingkat kecocokan tertinggi
                    if (!empty($rule_scores) && $rule_scores[0]['score'] >= 1) {
                        $top_rule = $rule_scores[0];
                        $diagnosa_id = $top_rule['kode_penyebab'];
                        $rule_terpakai = $top_rule['kode_rule'];

                        // Tarik data penyebab
                        $stmt_p = $conn->prepare("SELECT * FROM penyebab WHERE kode_penyebab = ?");
                        $stmt_p->bind_param("s", $diagnosa_id);
                        $stmt_p->execute();
                        $penyebab = $stmt_p->get_result()->fetch_assoc();

                        // PERBAIKAN: Tarik data solusi dengan JOIN ke tabel jembatan relasi_solusi
                        $stmt_s = $conn->prepare("SELECT s.kode_solusi, s.nama_solusi 
                                                  FROM solusi s 
                                                  JOIN relasi_solusi rs ON s.kode_solusi = rs.kode_solusi 
                                                  WHERE rs.kode_penyebab = ?");
                        $stmt_s->bind_param("s", $diagnosa_id);
                        $stmt_s->execute();
                        $solusi_res = $stmt_s->get_result();

                        echo '<div class="result-box">';
                        echo '<h3>Diagnosis Berhasil: ' . htmlspecialchars($penyebab['nama_penyebab']) . ' (' . $penyebab['kode_penyebab'] . ')</h3>';
                        echo '<p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 15px;">';
                        echo 'Mekanisme: Rule <strong>' . $rule_terpakai . '</strong> dipilih karena memiliki tingkat relevansi tertinggi dari total ' . count($working_memory) . ' gejala yang Anda input.';
                        echo '</p>';
                        echo '<strong>Rekomendasi Tindakan (Buku Psikologi Pendidikan):</strong>';
                        echo '<ul class="solusi-list">';
                        while ($sol = $solusi_res->fetch_assoc()) {
                            echo '<li><strong>[' . htmlspecialchars($sol['kode_solusi']) . ']</strong> ' . htmlspecialchars($sol['nama_solusi']) . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    } else {
                        echo '<div style="border: 1px solid #e2e8f0; background: rgba(255,255,255,0.02); padding: 30px; border-radius: 16px;">';
                        echo '<h3 style="color:var(--text-muted); margin-bottom:10px;">Hasil Konklusi Nihil</h3>';
                        echo '<p style="color:var(--text-muted);">Gejala yang Anda masukkan benar-benar di luar batasan data base sistem.</p>';
                        echo '</div>';
                    }
                }
                
                // Script otomatis scroll ke area hasil setelah klik tombol agar user tidak bingung
                echo '<script>
                    document.getElementById("hasil-analisis").scrollIntoView({ behavior: "smooth" });
                </script>';
            }
            ?>
        </div>
    </div>

    <script>
    function toggleCheckbox(element) {
        const checkbox = element.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
        checkbox.checked ? element.classList.add('selected') : element.classList.remove('selected');
    }
    function handleCheck(checkbox) {
        const parent = checkbox.closest('.gejala-item');
        checkbox.checked ? parent.classList.add('selected') : parent.classList.remove('selected');
    }
    </script>
    <footer style="text-align: center; padding: 30px 20px; margin-top: 60px; border-top: 1px solid var(--glass-border); background: rgba(11, 15, 25, 0.8);">
        <p style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500; letter-spacing: 0.5px;">
            Copyright © 2026 <span style="color: var(--text-main); font-weight: 600;">Julianus Aldy.S & Gregorius Nobel</span>. All Rights Reserved.
        </p>
    </footer>
</body>
</html>