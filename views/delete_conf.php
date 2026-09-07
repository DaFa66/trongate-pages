<h1>Delete Webpage</h1>
<?= flashdata() ?>
<?= validation_errors() ?>
<p>Are you sure you want to permanently delete the webpage <strong><?= out($page_title) ?></strong>? This action cannot be undone.</p>
<div class="card">
    <div class="card-body">
        <?php
        echo form_open($form_location);
        echo '<p>';
        echo anchor($cancel_url, 'Cancel', ['class' => 'button alt']);
        echo form_submit('submit', 'Yes - Delete Now');
        echo '</p>';
        echo form_close();
        ?>
    </div>
</div>
