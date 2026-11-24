<?php
require 'config.php';
if (empty($_SESSION['u'])) {
    go('index.php');
}
require 'includes/layout.php';

top('Blog', 'blog.php', false, ['blog.css']);

$posts = [
    ['Travel', 'Train Or Bus Journey? Which one suits?', 'kereta-bus.jpg'],
    ['Tips', 'Best Website to research for your next project', 'best-website.png'],
    ['Sports', 'How to Be a Dancer in 2023 with proper skills?', 'how-to-be-dancer.jpg'],
    ['Career', 'How to build strong portfolio and get a Job in UI/UX', 'portofolio.jpg'],
    ['Develop', 'How to start export import business from home?', 'business.jpg'],
    ['Campus', 'Belajar efektif: mengatur waktu tugas dan ujian', 'belajar-efektif.jpg'],
];

echo '<div class="blog">';
foreach ($posts as $post) {
    $kategori = $post[0];
    $judul    = $post[1];
    $gambar   = $post[2];
    $url_img  = BASE . '/images/' . $gambar;

    echo '<div>';
    echo '<div class="img"><img src="' . e($url_img) . '" alt="' . e($judul) . '" /></div>';
    echo '<small>' . e($kategori) . ' &middot; 13 March 2023</small>';
    echo '<h3>' . e($judul) . '</h3>';
    echo '<p>Capitalize on low hanging fruit to identify a ballpark value added activity to beta test. Override the digital divide with additional clickthroughs.</p>';
    echo '<a href="#">Read More...</a>';
    echo '</div>';
}
echo '</div>';

bottom();