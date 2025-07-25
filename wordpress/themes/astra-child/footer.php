<?php
use AstraChild\Core\Container;
$layout = Container::getContainer()->get(\AstraChild\Layouts\Layouts::class);
?>
<?= $layout->render('footer'); ?>
<?php wp_footer(); ?>
</body>

</html>