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
foreach ($posts as $p) {
    echo '<div>';
    echo '<div class="img"><img src="' . BASE . '/images/' . e($p[2]) . '" alt="' . e($p[1]) . '" /></div>';
    echo '<small>' . e($p[0]) . ' &middot; 13 March 2023</small>';
    echo '<h3>' . e($p[1]) . '</h3>';
    echo '<p>Capitalize on low hanging fruit to identify a ballpark value added activity to beta test. Override the digital divide with additional clickthroughs.</p>';
    echo '<a href="#">Read More...</a>';
    echo '</div>';
}
echo '</div>';

bottom();
?>