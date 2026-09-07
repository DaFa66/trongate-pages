<?php

/**
 * Pages model - database access for the pages module.
 */
class Pages_model extends Trongate {

    private string $table_name = 'pages';

    public function __construct(?string $module_name = null) {
        parent::__construct($module_name);
    }

    /**
     * Fetch the homepage record (the page served at the site root).
     */
    public function get_homepage(): object|bool {
        return $this->db->get_one_where('url_string', 'homepage', $this->table_name);
    }

    /**
     * Fetch a single page by its URL string (slug).
     */
    public function fetch_by_url_string(string $url_string): object|bool {
        return $this->db->get_one_where('url_string', $url_string, $this->table_name);
    }

    /**
     * Fetch a single page by id.
     */
    public function fetch_by_id(int $update_id): object|bool {
        return $this->db->get_where($update_id, $this->table_name);
    }

    public function count_all(): int {
        return $this->db->count($this->table_name);
    }

    /**
     * List pages, newest first.
     */
    public function fetch_records(int $limit, int $offset): array {
        $sql = 'SELECT * FROM pages ORDER BY id DESC LIMIT :limit OFFSET :offset';
        return $this->db->query_bind($sql, ['limit' => $limit, 'offset' => $offset], 'object');
    }

    public function count_search_results(string $search_query): int {
        $sql = 'SELECT COUNT(*) AS num_rows FROM pages WHERE page_title LIKE :needle OR page_body LIKE :needle';
        $rows = $this->db->query_bind($sql, ['needle' => '%' . $search_query . '%'], 'object');
        return (int) ($rows[0]->num_rows ?? 0);
    }

    public function search_records(string $search_query, int $limit, int $offset): array {
        $sql = 'SELECT * FROM pages
                WHERE page_title LIKE :needle OR page_body LIKE :needle
                ORDER BY id DESC LIMIT :limit OFFSET :offset';
        $params = [
            'needle' => '%' . $search_query . '%',
            'limit' => $limit,
            'offset' => $offset
        ];
        return $this->db->query_bind($sql, $params, 'object');
    }

    /**
     * Insert a new page record. Returns the new record id.
     */
    public function insert_page(array $data): int {
        return $this->db->insert($data, $this->table_name);
    }

    public function update_page(int $update_id, array $data): bool {
        return $this->db->update($update_id, $data, $this->table_name);
    }

    public function delete_page(int $update_id): bool {
        return $this->db->delete($update_id, $this->table_name);
    }

    /**
     * True when a slug is already taken by another page.
     */
    public function url_string_exists(string $url_string, int $ignore_id = 0): bool {
        $sql = 'SELECT id FROM pages WHERE url_string = :url_string AND id != :ignore_id LIMIT 1';
        $rows = $this->db->query_bind($sql, ['url_string' => $url_string, 'ignore_id' => $ignore_id], 'object');
        return count($rows) > 0;
    }

    /**
     * True when a page title is already taken by another page.
     */
    public function page_title_exists(string $page_title, int $ignore_id = 0): bool {
        $sql = 'SELECT id FROM pages WHERE page_title = :page_title AND id != :ignore_id LIMIT 1';
        $rows = $this->db->query_bind($sql, ['page_title' => $page_title, 'ignore_id' => $ignore_id], 'object');
        return count($rows) > 0;
    }

    /**
     * Map of admin user id => username for author display.
     * Uses the standard trongate_administrators table when present.
     */
    public function fetch_authors(array $user_ids): array {
        $authors = [];
        if (count($user_ids) === 0) {
            return $authors;
        }

        if (!$this->db->table_exists('trongate_administrators')) {
            return $authors;
        }

        $safe_ids = implode(',', array_map('intval', $user_ids));
        $sql = 'SELECT trongate_user_id, username FROM trongate_administrators WHERE trongate_user_id IN (' . $safe_ids . ')';
        $rows = $this->db->query($sql, 'object');
        foreach ($rows as $row) {
            $authors[(int) $row->trongate_user_id] = $row->username;
        }

        return $authors;
    }
}
