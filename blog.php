<?php
require 'config.php';
if (empty($_SESSION['u'])) {
    go('index.php');
}
require 'includes/layout.php';

top('Blog', 'blog.php', false, ['blog.css']);

$posts = [
    [
        'Travel',
        'Train Or Bus Journey? Which one suits?',
        'kereta-bus.jpg',
        'Perbandingan kenyamanan, waktu tempuh, dan biaya antara perjalanan kereta dan bus antarkota untuk berbagai kebutuhan perjalanan.'
    ],
    [
        'Tips',
        'Best Website to research for your next project',
        'best-website.png',
        'Kumpulan situs referensi dan riset praktis untuk membantu menyusun ide, data, serta alur kerja proyek baru secara lebih terarah.'
    ],
    [
        'Sports',
        'How to Be a Dancer in 2023 with proper skills?',
        'how-to-be-dancer.jpg',
        'Panduan dasar melatih ritme, kelenturan tubuh, dan konsistensi latihan agar teknik menari berkembang secara bertahap.'
    ],
    [
        'Career',
        'How to build strong portfolio and get a Job in UI/UX',
        'portofolio.jpg',
        'Langkah menyusun studi kasus desain yang jelas, menonjolkan proses berpikir, dan menyiapkan portofolio yang relevan untuk rekruter.'
    ],
    [
        'Develop',
        'How to start export import business from home?',
        'business.jpg',
        'Gambaran awal memulai usaha ekspor-impor dari rumah, mulai dari riset pasar, perizinan dasar, hingga koordinasi pengiriman barang.'
    ],
    [
        'Campus',
        'Belajar efektif: mengatur waktu tugas dan ujian',
        'belajar-efektif.jpg',
        'Strategi membagi prioritas tugas praktikum, catatan kuliah, dan jadwal belajar mingguan supaya tidak menumpuk menjelang ujian.'
    ],
];

echo '<div class="blog">';
foreach ($posts as $p) {
    echo '<div>';
    echo '<div class="img"><img src="' . BASE . '/images/' . e($p[2]) . '" alt="' . e($p[1]) . '" /></div>';
    echo '<small>' . e($p[0]) . ' &middot; 13 March 2023</small>';
    echo '<h3>' . e($p[1]) . '</h3>';
    echo '<p>' . e($p[3]) . '</p>';
    echo '<a href="#">Read More...</a>';
    echo '</div>';
}
echo '</div>';

bottom();
?>