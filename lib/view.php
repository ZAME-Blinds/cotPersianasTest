<?php

function render_header($title, array $options = [])
{
    $pageTitle = $title;
    $bodyClass = $options['bodyClass'] ?? '';
    $hideHeader = (bool) ($options['hideHeader'] ?? false);
    require __DIR__ . '/../templates/header.php';
}

function render_footer()
{
    require __DIR__ . '/../templates/footer.php';
}
