<?php

/**
 * Pages module - DB-backed webpages for Trongate v2.
 *
 * Public side:
 *   index()            homepage record (used when DEFAULT_MODULE = 'pages')
 *   display()          renders a page by its URL segment (slug)
 *   attempt_display()  404 interceptor entry point (ERROR_404 config)
 *
 * Admin side (all gated via trongate_security):
 *   manage / create / submit / submit_delete / toggle_publish
 *
 * Visual editor endpoints (admin session + CSRF header 'trongateToken'):
 *   update_page, fetch_page, element_adder, submit_beautify,
 *   check_youtube_video_id, fetch_uploaded_images, submit_image_upload,
 *   submit_delete_image, submit_create_new_img_folder, submit_rename_img_folder,
 *   submit_delete_folder
 */
class Pages extends Trongate {

    private int $default_limit = 20;
    private array $per_page_options = [10, 20, 50, 100];
    private int $max_file_size_mb = 5;
    private int $max_width = 4200;
    private int $max_height = 3200;

    private string $uploads_dir = 'modules/pages/images/uploads';
    private string $sample_text = 'Lorem ipsum, dolor sit amet, consectetur adipisicing elit. Sit sint perferendis a totam repellendus vitae architecto sunt obcaecati doloribus deserunt, unde, molestiae maxime. Enim adipisci officiis sit. Quasi, aliquam, facilis.';

    public function __construct(?string $module_name = null) {
        parent::__construct($module_name);
    }

    /**
     * Render the homepage record (the page with url_string = 'homepage').
     */
    public function index(): void {
        $page = $this->model->get_homepage();

        if ($page === false) {
            if (strtolower(ENV) === 'dev') {
                $this->create_homepage_record();
                $page = $this->model->get_homepage();
            }
            if ($page === false) {
                $this->render_not_found();
                return;
            }
        }

        $this->render_page($page, false);
    }

    /**
     * Display a page by URL. Handles both clean URLs (via ERROR_404
     * interception with original segments intact) and direct module URLs
     * (pages/display/some-slug). An 'edit' suffix switches admin editing on.
     */
    public function display(): void {
        $request_parts = $this->request_parts();

        if (count($request_parts) === 0) {
            // Site root reached display() directly - behave like the homepage.
            $this->index();
            return;
        }

        if (count($request_parts) > 2) {
            // Pages are single flat URL segments (as in v1).
            $this->render_not_found();
            return;
        }

        $edit_mode = false;
        $slug = $request_parts[0];

        if ((count($request_parts) === 2) && ($request_parts[1] === 'edit')) {
            $edit_mode = true;
        } elseif (count($request_parts) === 2) {
            $this->render_not_found();
            return;
        }

        $page = $this->model->fetch_by_url_string($slug);

        if ($page === false) {
            $this->render_not_found();
            return;
        }

        $this->render_page($page, $edit_mode);
    }

    /**
     * Request segments below the site root. Accepts clean URLs
     * (my-slug, my-slug/edit) and direct module URLs
     * (pages/display/my-slug) and normalises both.
     */
    private function request_parts(): array {
        $request_path = $this->request_path();
        $parts = ($request_path === '') ? [] : explode('/', $request_path);

        if ((count($parts) >= 2) && ($parts[0] === 'pages') && ($parts[1] === 'display')) {
            $parts = array_slice($parts, 2);
        }

        return $parts;
    }

    /**
     * Entry point used by the framework ERROR_404 config.
     */
    public function attempt_display(): void {
        $this->display();
    }

    /**
     * Admin list of pages.
     */
    public function manage(): void {
        $this->trongate_security->make_sure_allowed();

        $uploads_path = APPPATH . $this->uploads_dir;
        if (!is_dir($uploads_path) || !is_writable($uploads_path)) {
            $data = [
                'view_module' => 'pages',
                'view_file' => 'permissions_error'
            ];
            $this->templates->admin($data);
            return;
        }

        $search_query = trim((string) ($_GET['search_query'] ?? ''));
        $search_active = ($search_query !== '');

        $total_rows = $search_active
            ? $this->model->count_search_results($search_query)
            : $this->model->count_all();

        $limit = $this->get_limit();
        $offset = $this->get_offset();

        $rows = $search_active
            ? $this->model->search_records($search_query, $limit, $offset)
            : $this->model->fetch_records($limit, $offset);

        $data = [
            'rows' => $this->prepare_rows_for_display($rows),
            'headline' => 'Manage Webpages',
            'pagination_data' => $this->get_pagination_data($total_rows, $limit, $search_query),
            'search_query' => $search_query,
            'search_active' => $search_active,
            'per_page_options' => $this->per_page_options,
            'selected_per_page' => $this->get_selected_per_page(),
            'view_module' => 'pages',
            'view_file' => 'manage'
        ];

        $this->templates->admin($data);
    }

    /**
     * Display the "create page" form (title only - the editor is opened next).
     */
    public function create(): void {
        $this->trongate_security->make_sure_allowed();

        $data = [
            'headline' => 'Create New Webpage',
            'cancel_url' => 'pages/manage',
            'form_location' => 'pages/submit',
            'page_title' => (REQUEST_TYPE === 'POST') ? post('page_title', true) : '',
            'view_module' => 'pages',
            'view_file' => 'create'
        ];

        $this->templates->admin($data);
    }

    /**
     * Create a new (unpublished) page from a title, then open the editor.
     */
    public function submit(): void {
        $this->trongate_security->make_sure_allowed();

        $submit = post('submit', true);

        if ($submit !== 'Submit') {
            redirect('pages/manage');
            return;
        }

        $this->validation->set_rules('page_title', 'page title', 'required|min_length[2]|max_length[255]|callback_title_check');

        if ($this->validation->run()) {
            $page_title = trim(post('page_title', true));
            $slug = $this->make_unique_slug($page_title);

            $data = [
                'url_string' => $slug,
                'page_title' => $page_title,
                'meta_keywords' => '',
                'meta_description' => '',
                'page_body' => '<h1>' . out($page_title) . '</h1>',
                'date_created' => time(),
                'last_updated' => time(),
                'published' => 0,
                'created_by' => (int) $this->trongate_tokens->get_user_id()
            ];

            $update_id = $this->model->insert_page($data);
            set_flashdata('The webpage was created. You can now add content and publish it.');
            redirect($this->page_url($slug, true));
        } else {
            $this->create();
        }
    }

    /**
     * Confirmation screen before deleting a page.
     */
    public function delete_conf(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');
        $page = $this->model->fetch_by_id($update_id);

        if (($page === false) || ($page->url_string === 'homepage')) {
            redirect('pages/manage');
            return;
        }

        $data = [
            'update_id' => $update_id,
            'page_title' => $page->page_title,
            'cancel_url' => 'pages/manage',
            'form_location' => 'pages/submit_delete/' . $update_id,
            'view_module' => 'pages',
            'view_file' => 'delete_conf'
        ];

        $this->templates->admin($data);
    }

    /**
     * Delete a page (homepage protected).
     */
    public function submit_delete(): void {
        $this->trongate_security->make_sure_allowed();

        $update_id = segment(3, 'int');
        $submit = post('submit', true);

        if ($update_id === 0 || $submit !== 'Yes - Delete Now') {
            redirect('pages/manage');
            return;
        }

        if (!$this->validate_form_token()) {
            set_flashdata('Your session has expired. Please try again.');
            redirect('pages/manage');
            return;
        }

        $page = $this->model->fetch_by_id($update_id);

        if ($page === false) {
            redirect('pages/manage');
            return;
        }

        if ($page->url_string === 'homepage') {
            set_flashdata('Deletion of the homepage record is not permitted.');
            redirect('pages/manage');
            return;
        }

        $this->model->delete_page($update_id);
        set_flashdata('The record was successfully deleted');
        redirect('pages/manage');
    }

    /**
     * Publish / unpublish a page (MX form post; returns the new badge).
     */
    public function toggle_publish(): void {
        $this->trongate_security->make_sure_allowed();

        if (!$this->validate_form_token()) {
            http_response_code(400);
            echo 'Invalid security token. Please reload the page.';
            return;
        }

        $update_id = segment(3, 'int');
        $page = $this->model->fetch_by_id($update_id);

        if ($page === false) {
            http_response_code(404);
            echo 'Page not found.';
            return;
        }

        $new_value = ($page->published == 1) ? 0 : 1;
        $this->model->update_page($update_id, ['published' => $new_value, 'last_updated' => time()]);
        echo $this->published_badge($update_id, $new_value);
    }

    /**
     * Editor save endpoint: updates a page from the visual editor.
     * Accepts a JSON body: page_title, url_string, meta_keywords,
     * meta_description, published, page_body.
     */
    public function update_page(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $update_id = segment(3, 'int');
        $page = $this->model->fetch_by_id($update_id);

        if ($page === false) {
            http_response_code(400);
            echo 'The page could not be found.';
            return;
        }

        $body = $this->read_json_body();

        $page_title = trim((string) ($body['page_title'] ?? ''));
        $slug = strtolower(trim((string) ($body['url_string'] ?? '')));
        $page_body = (string) ($body['page_body'] ?? '');
        $meta_keywords = (string) ($body['meta_keywords'] ?? '');
        $meta_description = (string) ($body['meta_description'] ?? '');
        $published = (isset($body['published']) && (int) $body['published'] === 1) ? 1 : 0;

        if ($page_title === '') {
            http_response_code(400);
            echo 'A page title is required.';
            return;
        }

        if (!preg_match('/^[a-z0-9\-]+$/', $slug) || ($slug === '')) {
            http_response_code(400);
            echo 'The URL string may only contain lowercase letters, numbers and hyphens.';
            return;
        }

        if ($this->module_exists($slug)) {
            http_response_code(400);
            echo 'The URL string conflicts with an existing module name.';
            return;
        }

        if ($this->model->url_string_exists($slug, $update_id)) {
            http_response_code(400);
            echo 'That URL string is already in use by another webpage.';
            return;
        }

        if ($this->model->page_title_exists($page_title, $update_id)) {
            http_response_code(400);
            echo 'That page title is already in use by another webpage.';
            return;
        }

        $data = [
            'url_string' => $slug,
            'page_title' => $page_title,
            'meta_keywords' => $meta_keywords,
            'meta_description' => $meta_description,
            'page_body' => $page_body,
            'published' => $published,
            'last_updated' => time()
        ];

        $this->model->update_page($update_id, $data);
        http_response_code(200);
    }

    /**
     * Editor settings fetch: returns the current page record as JSON.
     */
    public function fetch_page(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $update_id = segment(3, 'int');
        $page = $this->model->fetch_by_id($update_id);

        if ($page === false) {
            http_response_code(404);
            echo 'Page not found.';
            return;
        }

        json([
            'url_string' => $page->url_string,
            'page_title' => $page->page_title,
            'meta_keywords' => $page->meta_keywords ?? '',
            'meta_description' => $page->meta_description ?? '',
            'published' => (int) $page->published
        ]);
    }

    /**
     * Element palette fragment for the visual editor.
     */
    public function element_adder(): void {
        $this->trongate_security->make_sure_allowed();

        $data = [
            'view_module' => 'pages',
            'view_file' => 'element_adder'
        ];
        $this->view('element_adder', $data);
    }

    /**
     * Beautify raw HTML (code view in the editor).
     */
    public function submit_beautify(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $body = $this->read_json_body();
        $page_body = (string) ($body['page_body'] ?? '');

        http_response_code(200);
        echo $this->beautify_html($page_body, '    ');
    }

    /**
     * Validate a YouTube URL or ID (editor video element).
     */
    public function check_youtube_video_id(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $body = $this->read_json_body();
        $video_id = trim((string) ($body['video_id'] ?? ''));

        $pattern = '/^(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:embed\/|watch\?v=|v\/|watch\?.+&v=|watch\/.+\/?)|youtu\.be\/)([^&\?\/\s]+)/';

        if (preg_match($pattern, $video_id, $matches) && strlen($matches[1]) === 11) {
            http_response_code(200);
            echo $matches[1];
            return;
        }

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $video_id)) {
            http_response_code(200);
            echo $video_id;
            return;
        }

        http_response_code(406);
        echo 'Invalid YouTube URL or video ID.';
    }

    /**
     * Media library listing: folders and images below the uploads root.
     */
    public function fetch_uploaded_images(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $body = $this->read_json_body();
        $current_dir = $this->safe_relative_dir((string) ($body['currentImgDir'] ?? ''));

        $full_path = APPPATH . $this->uploads_dir . $current_dir;

        $items = [];

        if (is_dir($full_path)) {
            $directories = $this->list_directories($full_path);
            foreach ($directories as $directory) {
                $items[] = ['info' => $directory, 'type' => 'directory'];
            }

            $images = $this->list_images($full_path);
            foreach ($images as $image) {
                $items[] = ['info' => $image, 'type' => 'image'];
            }
        }

        json($items);
    }

    /**
     * Image upload endpoint (multipart POST from the media manager).
     */
    public function submit_image_upload(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        if (!isset($_FILES['file1']) || !is_uploaded_file($_FILES['file1']['tmp_name'])) {
            http_response_code(400);
            echo 'No file was submitted.';
            return;
        }

        $file_tmp = $_FILES['file1']['tmp_name'];
        $original_name = $_FILES['file1']['name'];
        $current_dir = $this->safe_relative_dir((string) ($_POST['currentImgDir'] ?? ''));

        $validation = $this->validate_upload($file_tmp);
        if ($validation !== '') {
            http_response_code(400);
            echo $validation;
            return;
        }

        $destination_dir = APPPATH . $this->uploads_dir . $current_dir;

        if (!is_dir($destination_dir) || !is_writable($destination_dir)) {
            http_response_code(400);
            echo 'The target folder is not writable.';
            return;
        }

        $file_name = $this->prep_file_name($original_name, $destination_dir);

        if (move_uploaded_file($file_tmp, $destination_dir . '/' . $file_name)) {
            http_response_code(200);
            echo $this->asset_url($this->uploads_dir . $current_dir . '/' . $file_name);
        } else {
            http_response_code(500);
            echo 'The upload failed. Please check folder permissions.';
        }
    }

    /**
     * Delete an image from the media library.
     */
    public function submit_delete_image(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $body = $this->read_json_body();
        $file_name = basename((string) ($body['fileName'] ?? ''));
        $current_dir = $this->safe_relative_dir((string) ($body['currentImgDir'] ?? ''));

        if ($file_name === '') {
            http_response_code(400);
            echo 'Invalid file name.';
            return;
        }

        $file_path = APPPATH . $this->uploads_dir . $current_dir . '/' . $file_name;

        if (file_exists($file_path) && (strpos(mime_content_type($file_path), 'image/') === 0)) {
            unlink($file_path);
            http_response_code(200);
            echo 'Image deleted successfully.';
        } else {
            http_response_code(404);
            echo 'The image could not be found.';
        }
    }

    /**
     * Create a folder inside the media library.
     */
    public function submit_create_new_img_folder(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $body = $this->read_json_body();
        $folder_name = $this->sanitize_folder_name((string) ($body['newFolderName'] ?? ''));
        $current_dir = $this->safe_relative_dir((string) ($body['currentImgDir'] ?? ''));

        if ($folder_name === '') {
            http_response_code(400);
            echo 'Invalid folder name.';
            return;
        }

        $new_folder_path = APPPATH . $this->uploads_dir . $current_dir . '/' . $folder_name;

        if (is_dir($new_folder_path)) {
            http_response_code(400);
            echo 'Folder exists!';
            return;
        }

        mkdir($new_folder_path, 0755, true);
        file_put_contents($new_folder_path . '/index.php', '');
        http_response_code(200);
        echo $folder_name;
    }

    /**
     * Rename a folder inside the media library.
     */
    public function submit_rename_img_folder(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $body = $this->read_json_body();
        $old_folder_name = basename((string) ($body['oldFolderName'] ?? ''));
        $new_folder_name = $this->sanitize_folder_name((string) ($body['newFolderName'] ?? ''));
        $current_dir = $this->safe_relative_dir((string) ($body['currentImgDir'] ?? ''));

        if (($old_folder_name === '') || ($new_folder_name === '')) {
            http_response_code(400);
            echo 'Invalid folder name.';
            return;
        }

        $old_path = APPPATH . $this->uploads_dir . $current_dir . '/' . $old_folder_name;
        $new_path = APPPATH . $this->uploads_dir . $current_dir . '/' . $new_folder_name;

        if (!is_dir($old_path)) {
            http_response_code(400);
            echo 'Old folder does not exist!';
            return;
        }

        if (is_dir($new_path)) {
            http_response_code(400);
            echo 'New folder already exists!';
            return;
        }

        rename($old_path, $new_path);
        http_response_code(200);
        echo $new_folder_name;
    }

    /**
     * Delete a folder from the media library (recursively).
     */
    public function submit_delete_folder(): void {
        $this->trongate_security->make_sure_allowed();
        $this->require_editor_token();

        $body = $this->read_json_body();
        $folder_name = basename((string) ($body['folderName'] ?? ''));
        $current_dir = $this->safe_relative_dir((string) ($body['currentImgDir'] ?? ''));

        if ($folder_name === '') {
            http_response_code(400);
            echo 'Invalid folder name.';
            return;
        }

        $target_folder_path = APPPATH . $this->uploads_dir . $current_dir . '/' . $folder_name;

        if (!is_dir($target_folder_path)) {
            http_response_code(400);
            echo 'Folder does not exist!';
            return;
        }

        $this->delete_directory($target_folder_path);
        http_response_code(200);
    }

    /**
     * Validation callback: page titles must be unique and their derived slug
     * must not collide with an existing module folder.
     */
    public function title_check(string $str): string|bool {
        $page_title = trim(strip_tags($str));

        if ($this->model->page_title_exists($page_title)) {
            return 'The page title that you submitted is not available.';
        }

        $slug = url_title($page_title);

        if ($slug !== '' && $this->module_exists($slug)) {
            return 'The page title that you submitted conflicts with a pre-existing module named <b>' . out($slug) . '</b>.';
        }

        return true;
    }

    private function render_page(object $page, bool $edit_mode): void {
        $record_id = (int) $page->id;

        $data = (array) $page;
        $data['record_id'] = $record_id;
        $data['enable_page_edit'] = false;
        $data['invite_clear_home'] = 0;

        if ($edit_mode) {
            $this->trongate_security->make_sure_allowed();

            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }

            $data['enable_page_edit'] = true;
            $data['csrf_token'] = $_SESSION['csrf_token'];
            $data['img_upload_api'] = BASE_URL . 'pages/submit_image_upload';
            $data['sample_text'] = $this->sample_text;
        } else {
            $data['page_body'] = str_replace('[website]', BASE_URL, $data['page_body']);
        }

        $is_published = ((int) $page->published === 1);

        if (!$is_published && !$edit_mode) {
            $this->render_draft_page($data);
            return;
        }

        $data['view_module'] = 'pages';
        $data['view_file'] = 'display';
        $this->templates->public($data);
    }

    private function render_draft_page(array $data): void {
        http_response_code(404);

        if (strtolower(ENV) !== 'dev') {
            $this->render_not_found();
            return;
        }

        // Dev mode: show a friendly explanation instead of a bare 404.
        $data['view_module'] = 'pages';
        $data['view_file'] = 'not_published';
        $this->templates->public($data);
    }

    private function render_not_found(): void {
        http_response_code(404);
        $this->module('templates');
        $this->templates->error_404();
        die();
    }

    private function create_homepage_record(): void {
        $data = [
            'url_string' => 'homepage',
            'page_title' => 'Homepage',
            'meta_keywords' => '',
            'meta_description' => '',
            'date_created' => time(),
            'last_updated' => 0,
            'published' => 1,
            'created_by' => 0
        ];

        $view_data = [
            'view_module' => 'pages',
            'view_file' => 'default_homepage_content'
        ];
        $data['page_body'] = $this->view('default_homepage_content', $view_data, true);

        $this->model->insert_page($data);
    }

    /**
     * The path portion of the current URL, relative to BASE_URL.
     */
    private function request_path(): string {
        $current_url = current_url();
        $current_url = preg_replace('/\?.*$/', '', $current_url);
        $base = rtrim(BASE_URL, '/');

        if ($current_url === $base) {
            return '';
        }

        $path = substr($current_url, strlen($base));
        return trim($path, '/');
    }

    /**
     * Public URL for a page slug. Clean URLs are used when the app has
     * ERROR_404 wired to this module; otherwise the direct module URL.
     */
    private function page_url(string $slug, bool $edit = false): string {
        $clean_urls = defined('ERROR_404') && (ERROR_404 === 'pages/attempt_display');
        $url = $clean_urls ? BASE_URL . $slug : BASE_URL . 'pages/display/' . $slug;

        if ($edit) {
            $url .= '/edit';
        }

        return $url;
    }

    private function prepare_rows_for_display(array $rows): array {
        if (count($rows) === 0) {
            return $rows;
        }

        $user_ids = [];
        foreach ($rows as $row) {
            $user_ids[] = (int) $row->created_by;
        }

        $authors = $this->model->fetch_authors(array_unique($user_ids));

        foreach ($rows as $key => $row) {
            $published_int = (int) $row->published;
            $rows[$key]->published = ($published_int === 1) ? 'yes' : 'no';
            $rows[$key]->author = $authors[(int) $row->created_by] ?? 'Unknown';
            $rows[$key]->webpage_url = $this->page_url($row->url_string);
            $rows[$key]->edit_url = $this->page_url($row->url_string, true);
            $rows[$key]->published_badge = $this->published_badge((int) $row->id, $published_int);
        }

        return $rows;
    }

    private function published_badge(int $update_id, int $published): string {
        $label = ($published === 1) ? 'Published' : 'Draft';
        $class = ($published === 1) ? 'pub-yes' : 'pub-no';
        $html = '<span id="pub-' . $update_id . '" class="pub-badge ' . $class . '">' . $label . '</span>';

        return $html;
    }

    private function module_exists(string $slug): bool {
        return is_dir(APPPATH . 'modules/' . $slug);
    }

    /**
     * Create a unique slug from a page title (appends suffix words on clash).
     */
    private function make_unique_slug(string $page_title): string {
        $slug = url_title($page_title);
        $candidate = $slug;
        $suffixes = [
            'information', 'advice', 'details', 'insights', 'data',
            'tips', 'facts', 'knowledge', 'solutions', 'resources',
            'overview', 'explanation', 'learn-more', 'deep-dive',
            'how-to', 'best-practices', 'explore', 'in-depth'
        ];

        $iteration = 0;
        while ($this->slug_taken($candidate) && $iteration < 10) {
            $candidate = $slug . '-' . $suffixes[array_rand($suffixes)];
            $iteration++;
        }

        if ($iteration >= 10) {
            $candidate = $slug . '-' . make_rand_str(8);
        }

        return $candidate;
    }

    private function slug_taken(string $slug): bool {
        if ($this->module_exists($slug)) {
            return true;
        }

        return $this->model->url_string_exists($slug);
    }

    // --- editor request helpers -------------------------------------------

    private function require_editor_token(): void {
        $submitted_token = $_SERVER['HTTP_TRONGATETOKEN'] ?? '';
        $expected_token = $_SESSION['csrf_token'] ?? '';

        if (($submitted_token === '') || ($expected_token === '') || !hash_equals($expected_token, $submitted_token)) {
            http_response_code(400);
            echo 'Invalid or missing security token. Please reload the page.';
            die();
        }
    }

    /**
     * CSRF check for ordinary form posts (hidden csrf_token field).
     */
    private function validate_form_token(): bool {
        $submitted_token = post('csrf_token');
        $expected_token = $_SESSION['csrf_token'] ?? '';

        return (($submitted_token !== '') && ($expected_token !== '') && hash_equals($expected_token, $submitted_token));
    }

    private function read_json_body(): array {
        $posted_data = file_get_contents('php://input');
        $decoded = json_decode($posted_data, true);

        if (!is_array($decoded)) {
            http_response_code(400);
            echo 'Invalid inbound JSON.';
            die();
        }

        return $decoded;
    }

    // --- media helpers ------------------------------------------------------

    private function uploads_root(): string {
        return APPPATH . $this->uploads_dir;
    }

    /**
     * Normalise a submitted media sub-directory to a safe, rooted relative
     * path (single leading slash kept off, no traversal, no backslashes).
     */
    private function safe_relative_dir(string $submitted_dir): string {
        $dir = str_replace('\\', '/', trim($submitted_dir));
        $dir = trim($dir, '/');

        if ($dir === '') {
            return '';
        }

        $parts = explode('/', $dir);
        $clean_parts = [];
        foreach ($parts as $part) {
            if (($part === '..') || ($part === '.')) {
                continue;
            }
            if (preg_match('/^[a-zA-Z0-9_\- ]+$/', $part)) {
                $clean_parts[] = $part;
            }
        }

        $clean = implode('/', $clean_parts);

        // Refuse anything that would escape the uploads root.
        $real_root = realpath($this->uploads_root());
        $real_target = realpath($this->uploads_root() . '/' . $clean);

        if (($real_root === false) || (($real_target !== false) && (strpos($real_target, $real_root) !== 0))) {
            return '';
        }

        return $clean;
    }

    private function sanitize_folder_name(string $name): string {
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        $name = preg_replace('~[^\pL\d]+~u', '_', $name);
        $name = trim($name, '_ ');
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_\-]/', '', $name);

        return $name;
    }

    private function list_directories(string $path): array {
        $directories = [];

        if (!is_dir($path)) {
            return $directories;
        }

        $entries = scandir($path);
        foreach ($entries as $entry) {
            if (($entry === '.') || ($entry === '..')) {
                continue;
            }
            if (is_dir($path . '/' . $entry)) {
                $directories[] = $entry;
            }
        }

        sort($directories);
        return $directories;
    }

    private function list_images(string $path): array {
        $images = [];

        if (!is_dir($path)) {
            return $images;
        }

        $entries = scandir($path);
        foreach ($entries as $entry) {
            $file_path = $path . '/' . $entry;
            if (is_file($file_path) && in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $images[] = [
                    'file_name' => $entry,
                    'date_uploaded' => filemtime($file_path),
                    'file_size' => filesize($file_path),
                    'url' => $this->asset_url($this->uploads_dir . '/' . $entry)
                ];
            }
        }

        usort($images, static function (array $a, array $b): int {
            return strcasecmp($a['file_name'], $b['file_name']);
        });

        return $images;
    }

    private function asset_url(string $relative_path): string {
        $module_trigger = defined('MODULE_ASSETS_TRIGGER') ? MODULE_ASSETS_TRIGGER : '_module';
        $public_path = 'pages' . $module_trigger . '/' . str_replace('modules/pages/', '', $relative_path);

        return BASE_URL . $public_path;
    }

    private function validate_upload(string $file_tmp): string {
        if (!is_file($file_tmp)) {
            return 'No file was submitted.';
        }

        $file_size_mb = round(filesize($file_tmp) / 1048576, 2);
        if ($file_size_mb > $this->max_file_size_mb) {
            return 'The uploaded file exceeds the maximum allowed file size of ' . $this->max_file_size_mb . 'MB.';
        }

        $image_size = @getimagesize($file_tmp);
        if ($image_size === false) {
            return 'The uploaded file is not a valid image.';
        }

        if ($image_size[0] > $this->max_width) {
            return 'The uploaded image width exceeds the maximum allowed width of ' . $this->max_width . 'px.';
        }

        if ($image_size[1] > $this->max_height) {
            return 'The uploaded image height exceeds the maximum allowed height of ' . $this->max_height . 'px.';
        }

        return '';
    }

    private function prep_file_name(string $original_file_name, string $destination_dir): string {
        $extension = strtolower(pathinfo($original_file_name, PATHINFO_EXTENSION));
        $base_name = pathinfo($original_file_name, PATHINFO_FILENAME);
        $slug = str_replace('-', '_', url_title($base_name));
        $slug = substr($slug, 0, 33);

        $file_name = $slug . '.' . $extension;
        $counter = 1;
        while (file_exists($destination_dir . '/' . $file_name)) {
            $counter++;
            $file_name = $slug . $counter . '.' . $extension;
        }

        return $file_name;
    }

    private function delete_directory(string $target_folder_path): void {
        if (!is_dir($target_folder_path)) {
            return;
        }

        $entries = scandir($target_folder_path);
        foreach ($entries as $entry) {
            if (($entry === '.') || ($entry === '..')) {
                continue;
            }
            $path = $target_folder_path . '/' . $entry;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($target_folder_path);
    }

    private function beautify_html(string $content, string $tab = "\t"): string {
        $content = str_replace('  ', ' ', $content);
        $content = preg_replace('/(>)(<)(\/*)/', "$1\n$2$3", $content);

        $result = '';
        $pad = 0;
        $token = strtok($content, "\n");

        while (($token !== false) && (strlen($token) > 0)) {
            $token = trim($token);
            $indent = 0;

            if (preg_match('/^<\/\w/', $token)) {
                $pad--;
            } elseif (preg_match('/^<\w[^>]*[^\/]>.*$/', $token)) {
                if (!preg_match('/^<(area|base|br|col|command|embed|hr|img|input|keygen|link|meta|param|source|track|wbr)\b/i', $token)) {
                    $indent = 1;
                }
            }

            if (($token === '<textarea>') || ($token === '</textarea>')) {
                $result .= $token . "\n";
            } else {
                $result .= str_pad($token, strlen($token) + $pad, $tab, STR_PAD_LEFT) . "\n";
            }

            $pad += $indent;
            $token = strtok("\n");
        }

        return $result;
    }

    // --- pagination helpers (kools-style) -----------------------------------

    private function get_limit(): int {
        $selected_index = $this->get_selected_per_page();
        return $this->per_page_options[$selected_index] ?? $this->default_limit;
    }

    private function get_offset(): int {
        $page_num = (int) segment(3);

        if ($page_num > 1) {
            return ($page_num - 1) * $this->get_limit();
        }

        return 0;
    }

    private function get_selected_per_page(): int {
        $selected = $_SESSION['selected_per_page'] ?? 1;
        return (int) $selected;
    }

    public function set_per_page(): void {
        $this->trongate_security->make_sure_allowed();

        $selected_index = segment(3, 'int');
        if (!isset($this->per_page_options[$selected_index])) {
            $selected_index = 1;
        }

        $_SESSION['selected_per_page'] = $selected_index;
        redirect('pages/manage');
    }

    private function get_pagination_data(int $total_rows, int $limit, string $search_query = ''): array {
        $pagination_query = '';
        if ($search_query !== '') {
            $pagination_query = 'search_query=' . urlencode($search_query);
        }

        return [
            'total_rows' => $total_rows,
            'limit' => $limit,
            'pagination_root' => 'pages/manage',
            'pagination_query' => $pagination_query,
            'record_name_plural' => 'webpages',
            'include_showing_statement' => true
        ];
    }
}
