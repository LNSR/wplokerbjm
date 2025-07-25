<?php
use AstraChild\Core\Container;
$layout = Container::getContainer()->get(\AstraChild\Layouts\Layouts::class);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
    <?= $layout->render('header'); ?>