<div class="page-content">
    <?= flashdata() ?>
    <?= validation_errors() ?>
    <?= $page_body ?>
</div>
<?php
if (($enable_page_edit ?? false) === true) {
    include __DIR__ . '/enable_page_edit.php';
}
?>
