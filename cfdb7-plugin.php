<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * integration with CFDB7 plugin
 */

class CF7_Sheets_CFDB7_Plugin
{
    public function init()
    {
        add_action( 'cfdb7_after_formdetails', array( $this, 'show_button' ) );
        add_action( 'cfdb7_admin_init', array( $this, 'send_submission' ) );
    }
    
    public function show_button($form_post_id)
    {
        // #PLUGIN-CHECK: this action is called by CFDB7 plugin - hence nonce verification is not needed here
        if (!isset($_REQUEST['fid']) || !isset($_REQUEST['ufid'])) {
            return;
        }

        $fid = absint($_REQUEST['fid']);
        $ufid = absint($_REQUEST['ufid']);
        $nonce = wp_create_nonce('cf7-sheets');

        echo "<a href=admin.php?page=cfdb7-list.php&fid=" . $fid . "&ufid=" . $ufid . "&cf7-sheets=true&nonce=" . $nonce . " class='button'>";
        echo esc_html__('Send to Google Sheets', 'cf7-google-sheets');
        echo '</a>';
        
        if( isset($_REQUEST['cf7-sheets-status']) ) {
            if ($_REQUEST['cf7-sheets-status'] == 'true') {
                echo '<p>Submission successfully sent to Google Sheets</p>';
            } else {
                echo '<p>Failed to send submission to Google Sheets</p>';
            }
        }

        echo "<br>&nbsp;<br>";
        echo "<a href=admin.php?page=cfdb7-list.php&fid=" . $fid . "> &lt; ";
        echo esc_html__('Go back', 'cf7-google-sheets');
        echo '</a>';
        
    }

    public function send_submission()
    {
        if( !isset($_REQUEST['cf7-sheets']) || ( $_REQUEST['cf7-sheets'] != 'true' ) || !isset( $_REQUEST['nonce'] ) ) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_REQUEST['nonce']));
        if ( ! wp_verify_nonce( $nonce, 'cf7-sheets' ) ) wp_die('Invalid nonce.');

        if (!isset($_REQUEST['fid']) || !isset($_REQUEST['ufid'])) {
            print 'Sorry, your request is missing mandatory parameters.';
            exit;
        }

        $fid = absint($_REQUEST['fid']);
        $form_data = get_post_meta( $fid, 'gs_settings' );

        if ( empty( $form_data[0]['sheet-id'] ) || ( empty( $form_data[0]['tab-id'] ) && ( $form_data[0]['tab-id'] !== '0' ) ) ) {
            return;
        }

        global $wpdb;
        $cfdb = apply_filters( 'cfdb7_database', $wpdb );
        $table_name = $cfdb->prefix.'db7_forms';

        $ufid = absint($_REQUEST['ufid']);
        $results = $cfdb->get_results("SELECT form_value, form_date FROM $table_name
            WHERE form_id = '$ufid' ORDER BY form_id DESC LIMIT 1",OBJECT);

        $status = false;
        foreach ($results as $result) {
            $meta  = array(
                'date' => $result->form_date,
                'datetime' => $result->form_date
            );
            $data = array();
            $resultTmp = unserialize( $result->form_value );
            foreach ($resultTmp as $key => $value) {
                if (strpos($key, 'cfdb7_') !== false ) continue;

                $matches = array();
                preg_match('/^_.*$/m', $key, $matches);
                if( ! empty($matches[0]) ) continue;
                
                $value = str_replace( 
                                    array('&quot;','&#039;','&#047;','&#092;'),
                                    array('"',"'",'/','\\'), $value 
                                );

                if ( is_array($value) ) {
                    $value = implode(', ', $value);
                }

                $data[$key] = $value;
            }
            
            $client = new CF7_Sheets_Client();
            if ($client->add_row($form_data[0]['sheet-id'], $form_data[0]['tab-id'], $data, $meta))
                $status = true;
        }

        header("Location: admin.php?page=cfdb7-list.php&fid=" . $fid . "&ufid=" . $ufid . "&cf7-sheets-status=" . ($status ? 'true' : 'false'));
        die();
    }
}
