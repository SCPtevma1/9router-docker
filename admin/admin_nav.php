<?php
declare(strict_types=1);

function render_admin_nav(string $active, string $base = ''): void
{
    $items = [
        'overview' => ['label' => 'ภาพรวม', 'href' => 'dashboard.php'],
        'news' => ['label' => 'ข่าวสาร', 'href' => 'Management%20Modules/news_list.php'],
        'personnel' => ['label' => 'บุคลากร', 'href' => 'Management%20Modules/personnel_directory.php'],
        'courses' => ['label' => 'หลักสูตร', 'href' => 'Management%20Modules/courses_list.php'],
        'members' => ['label' => 'สมาชิก', 'href' => 'members_list.php?type=all'],
        'contact' => ['label' => 'ติดต่อ', 'href' => 'contact_messages.php'],
        'footer' => ['label' => 'Footer', 'href' => 'footer_manager.php'],
        'settings' => ['label' => 'ตั้งค่า', 'href' => 'settings.php'],
    ];

    echo '<nav aria-label="เมนูผู้ดูแล">';
    foreach ($items as $key => $item) {
        $current = $key === $active ? ' aria-current="page"' : '';
        echo '<a href="' . escape($base . $item['href']) . '"' . $current . '>'
            . escape($item['label']) . '</a>';
    }
    echo '</nav>';
}
