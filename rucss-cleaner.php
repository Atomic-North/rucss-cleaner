<?php
/**
 * Plugin Name: RUCSS Cleaner (AJAX)
 * Plugin URI: https://striding.co
 * Description: A simple plugin to delete rows from wp_sitemeta with a specific meta_key, in blocks of 1000, until it times out. Updates the page using AJAX.
 * Version: 1.0.3
 * Author: Luke Kirkwood
 * Author URI: https://striding.co
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Add admin menu item
function rucss_cleaner_admin_menu() {
    add_submenu_page(
        'tools.php',
        'RUCSS Cleaner',
        'RUCSS Cleaner',
        'manage_options',
        'rucss-cleaner',
        'rucss_cleaner_admin_page'
    );
}
add_action('admin_menu', 'rucss_cleaner_admin_menu');

// Display admin page
function rucss_cleaner_admin_page() {
    ?>
    <div class="wrap">
        <h1>RUCSS Cleaner</h1>
        <div id="rucss-cleaner-results"></div>
        <button id="rucss-cleaner-submit" class="button button-primary">Clean RUCSS Data (1000 rows per loop until timeout)</button>
    </div>
    <script>
    document.getElementById('rucss-cleaner-submit').addEventListener('click', function() {
        var button = this;
        button.disabled = true;
        var resultsDiv = document.getElementById('rucss-cleaner-results');
        resultsDiv.innerHTML = 'Cleaning RUCSS data...';

        function deleteRows() {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.status === 'error') {
                        resultsDiv.innerHTML = 'Error: ' + response.message;
                        button.disabled = false;
                    } else if (response.status === 'done') {
                        resultsDiv.innerHTML = 'Deleted ' + response.total_deleted_rows + ' rows successfully.';
                        button.disabled = false;
                    } else {
                        resultsDiv.innerHTML = 'Deleted ' + response.total_deleted_rows + ' rows so far...';
                        deleteRows();
                    }
                }
            };
            xhr.send('_ajax_nonce=<?php echo wp_create_nonce('rucss_cleaner_action'); ?>&action=rucss_cleaner_delete_rows');
        }

        deleteRows();
    });
    </script>
    <?php
}

// AJAX handler to process row deletion
function rucss_cleaner_ajax_handler() {
    check_ajax_referer('rucss_cleaner_action', '_ajax_nonce', true);

    // Set the max_execution_time to 5 minutes (300 seconds)
    ini_set('max_execution_time', 300);

    global $wpdb;

    $query = "DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '%rocket_rucss_warmup_resource_fetcher_batch_%' LIMIT 1000";
    $result = $wpdb->query($query);

    if ($result === false) {
        wp_send_json(array(
            'status' => 'error',
            'message' => 'Error deleting rows.',
        ));
    } elseif ($result === 0) {
        wp_send_json(array(
            'status' => 'done',
            'total_deleted_rows' => intval($_POST['total_deleted_rows']),
        ));
    } else {
        $total_deleted_rows = intval($_POST['total_deleted_rows']) + $result;
        wp_send_json(array(
            'status' => 'more',
            'total_deleted_rows' => $total_deleted_rows,
        ));
    }
}
add_action('wp_ajax_rucss_cleaner_delete_rows', 'rucss_cleaner_ajax_handler');
