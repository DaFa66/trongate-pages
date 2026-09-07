<h1><?= $headline ?></h1>
<?= flashdata() ?>
<?= validation_errors() ?>
<?php
echo '<p>';
echo anchor('pages/create', 'Create New Webpage', ['class' => 'button alt']);
echo '</p>';

if (count($rows) === 0) {
    echo '<p>There are currently no webpages to display.</p>';
    return;
}

echo Modules::run('pagination/display', $pagination_data);
?>

<div class="table-container">
    <table class="records-table">
        <thead>
            <tr>
                <th colspan="6">
                    <div>
                        <div>
                            <?php
                            echo '<form action="' . BASE_URL . 'pages/manage" method="get" class="inline-form">';
                            echo form_input('search_query', $search_query, ['id' => 'search_query', 'placeholder' => 'Search webpages...', 'autocomplete' => 'off']);
                            echo ' ' . form_submit('submit', 'Search', ['class' => 'alt']);
                            echo '</form>';
                            ?>
                        </div>
                        <div>Records Per Page: <?php
                            $dropdown_attr['onchange'] = 'setPerPage()';
                            echo form_dropdown('per_page', $per_page_options, $selected_per_page, $dropdown_attr);
                            ?></div>
                    </div>
                </th>
            </tr>
            <tr>
                <th>Webpage</th>
                <th>Author</th>
                <th>Created</th>
                <th>Last Updated</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <div><?= anchor($row->edit_url, out($row->page_title)) ?></div>
                        <div class="xs"><?= anchor($row->webpage_url, out($row->url_string)) ?></div>
                    </td>
                    <td><?= out($row->author) ?></td>
                    <td><?= date('j M Y', $row->date_created) ?></td>
                    <td><?= ($row->last_updated === 0) ? 'Never' : date('j M Y H:i', $row->last_updated) ?></td>
                    <td>
                        <span id="pub-<?= $row->id ?>"><?= $row->published_badge ?></span>
                        <?php
                        echo form_open('pages/toggle_publish/' . $row->id, [
                            'mx-post' => 'pages/toggle_publish/' . $row->id,
                            'mx-target' => '#pub-' . $row->id,
                            'mx-swap' => 'innerHTML'
                        ]);
                        echo form_submit('submit', 'Toggle', ['class' => 'alt tiny']);
                        echo form_close();
                        ?>
                    </td>
                    <td>
                        <div class="actions">
                            <?= anchor($row->edit_url, 'Edit', ['class' => 'button alt']) ?>
                            <?php
                            if ($row->url_string !== 'homepage') {
                                echo anchor('pages/delete_conf/' . $row->id, 'Delete', ['class' => 'button alt']);
                            }
                            ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
if (count($rows) > 9) {
    unset($pagination_data['include_showing_statement']);
    echo Modules::run('pagination/display', $pagination_data);
}
?>
<script>
  function setPerPage() {
    const selected = document.getElementById('per_page');
    if (selected) {
      window.location.href = '<?= BASE_URL ?>pages/set_per_page/' + selected.value;
    }
  }
</script>
