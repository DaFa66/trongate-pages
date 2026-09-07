<h1><?= $headline ?></h1>
<?= flashdata() ?>
<?= validation_errors() ?>
<p>Give your new webpage a title. You will be taken straight into the visual editor where you can add content and publish the page.</p>
<div class="card">
    <div class="card-body">
        <?php
        echo form_open($form_location);

        echo form_label('Page Title');
        $title_attr = [
            'placeholder' => 'Enter a page title here...',
            'autocomplete' => 'off',
            'required' => true,
            'minlength' => '2',
            'maxlength' => '255'
        ];
        echo form_input('page_title', $page_title, $title_attr);

        echo '<div class="text-right">';
        echo anchor($cancel_url, 'Cancel', ['class' => 'button alt']);
        echo form_submit('submit', 'Submit');
        echo '</div>';

        echo form_close();
        ?>
    </div>
</div>
