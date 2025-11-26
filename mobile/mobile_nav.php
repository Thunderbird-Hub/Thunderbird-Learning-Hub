<?php
if (!isset($mobile_active_page) || !$mobile_active_page) {
    $mobile_active_page = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '.php');
}

$nav_items = [
    'index' => [
        'href' => '/mobile/index.php',
        'icon' => '🏠',
        'label' => 'Home',
        'target' => 'home',
    ],
    'categories' => [
        'href' => '/mobile/categories.php',
        'icon' => '📂',
        'label' => 'Categories',
        'target' => 'categories',
    ],
    'training' => [
        'href' => '/mobile/training.php',
        'icon' => '🎓',
        'label' => 'Training',
        'target' => 'training',
    ],
    'profile' => [
        'href' => '/mobile/profile.php',
        'icon' => '👤',
        'label' => 'Profile',
        'target' => 'profile',
    ],
];
?>
<nav class="mobile-tab-bar" aria-label="Mobile navigation">
    <?php foreach ($nav_items as $key => $item): ?>
        <a href="<?php echo htmlspecialchars($item['href']); ?>" data-target="<?php echo htmlspecialchars($item['target']); ?>" class="<?php echo $mobile_active_page === $key ? 'active' : ''; ?>">
            <span class="icon"><?php echo $item['icon']; ?></span>
            <span><?php echo htmlspecialchars($item['label']); ?></span>
        </a>
    <?php endforeach; ?>
</nav>
