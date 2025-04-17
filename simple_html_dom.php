<?php

/**
  * Plugin Name: Support Mugshots
  * Description: Display Mugshots
  * Version: 1.0.0
*/
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function mugshots_content($atts, $content = '') {
    $wp_upload_dir = wp_upload_dir();

    if ( !is_dir($wp_upload_dir['basedir'] . '/mugshots') ) {
        wp_mkdir_p($wp_upload_dir['basedir'] . '/mugshots');
    }

    $atts = shortcode_atts([
        'period' => ''
    ], $atts);

    $wp_upload_dir = wp_upload_dir();

    if ( !is_dir($wp_upload_dir['basedir'] . '/mugshots') ) {
        wp_mkdir_p($wp_upload_dir['basedir'] . '/mugshots');
    }

    $mugshots_data = json_decode(file_get_contents( $wp_upload_dir['basedir'] . "/mugshots/mugshots" . ($atts['period' ] == 'last-7-days' ? '_last_7_days' : '')  .".json" ));
    
    $content = '<div class="mugshots-wrapper">
        <div class="mugshots-items">';
    foreach ($mugshots_data as $mugshot_item) {
        $content .= '<div class="mugshots-item">
            <div class="mugshots-left-side">
                <img class="mugshot-image" src="' . $wp_upload_dir['baseurl'] . '/mugshots/' . ($mugshot_item->avatar ? $mugshot_item->avatar : 'NoImageAvailable.jpg"') . '" />
            </div>
            <div class="mugshots-right-side">
                <div class="mugshots-name"><h4>' . $mugshot_item->name . '</h4></div>
                <div class="mugshots-description"><strong>' . $mugshot_item->description . '</strong></div>
                <div class="mugshots-date"><strong>Arrested:</strong> ' . $mugshot_item->date . '</div>
                <div class="mugshots-demographics"><strong>Demographics:</strong> ' . $mugshot_item->demographics . '</div>
                <div class="mugshots-location"><strong>Location:</strong> ' . $mugshot_item->location . '</div>
                <div class="mugshots-agency"><strong>Agency:</strong> ' . $mugshot_item->agency . '</div>
            </div>
        </div>';
    }
    $content .= '</div>
        <div class="mugshots-controller">
            <div class="button btn-prev">&lt; Previous</div>
            <div class="button btn-next">Next &gt;</div>
        </div>
    </div>
    <style>
        .mugshots-wrapper .mugshots-items {
            margin-bottom: 1.5rem;
        }
        .mugshots-wrapper .mugshots-items .mugshots-item {
            display: none;
        }
        .mugshots-wrapper .mugshots-items .mugshots-item.active {
            display: block;
        }
        .mugshots-wrapper .mugshots-items .mugshots-item .mugshots-right-side {
            line-height: 1.75;
        }
        @media (min-width: 768px) {
            .mugshots-wrapper .mugshots-items .mugshots-item.active {
                display: flex;
                flex-direction: row;
                column-gap:  1rem;
            }
            .mugshots-wrapper .mugshots-items .mugshots-item .mugshots-left-side,
            .mugshots-wrapper .mugshots-items .mugshots-item .mugshots-right-side {
                position: relative;
                width: 100%;
            }
            .mugshots-wrapper .mugshots-items .mugshots-item .mugshots-left-side {
                -ms-flex: 0 0 200px;
                flex: 0 0 200px;
                max-width: 200px;
            }
        }
    </style>
    <script>
        jQuery(document).ready(function() {
            let mugshots_active_index = 0;
            jQuery(jQuery(".mugshots-wrapper .mugshots-items .mugshots-item")[mugshots_active_index]).addClass("active");

            jQuery(".mugshots-wrapper .mugshots-controller .btn-prev").click(function() {
                jQuery(".mugshots-wrapper .mugshots-items .mugshots-item.active").removeClass("active");
                if (mugshots_active_index === 0) {
                    mugshots_active_index = jQuery(".mugshots-wrapper .mugshots-items .mugshots-item").length;
                }
                mugshots_active_index = mugshots_active_index - 1;
                jQuery(jQuery(".mugshots-wrapper .mugshots-items .mugshots-item")[mugshots_active_index]).addClass("active");
            });
            jQuery(".mugshots-wrapper .mugshots-controller .btn-next").click(function() {
                jQuery(".mugshots-wrapper .mugshots-items .mugshots-item.active").removeClass("active");
                if (mugshots_active_index === jQuery(".mugshots-wrapper .mugshots-items .mugshots-item").length - 1) {
                    mugshots_active_index = -1;
                }
                mugshots_active_index = mugshots_active_index + 1;
                jQuery(jQuery(".mugshots-wrapper .mugshots-items .mugshots-item")[mugshots_active_index]).addClass("active");
            });
        });
    </script>';

    return $content;
}
add_shortcode('mugshots', 'mugshots_content');

// Schedule the event on plugin/theme load
function mugshot_cron_schedule() {
    if ( ! wp_next_scheduled( 'mugshot_cron_hook' ) ) {
        wp_schedule_event( time(), 'daily', 'mugshot_cron_hook' );
    }
}
add_action( 'wp', 'mugshot_cron_schedule' );

// The function to be executed on cron
function mugshot_cron_job_function() {
    include( __DIR__ . '/simple_html_dom.php' );

    $wp_upload_dir = wp_upload_dir();

    if ( !is_dir($wp_upload_dir['basedir'] . '/mugshots') ) {
        wp_mkdir_p($wp_upload_dir['basedir'] . '/mugshots');
    }
    
    $mugshots = [];
    $mugshots_html = file_get_html( 'https://jocoreport.com/mugshots/');
    $mugshots_items = $mugshots_html->find('.carousel-inner .carousel-item');
    foreach ($mugshots_items as $mugshots_item) {
        $mugshot_item_avatar = $mugshots_item->find('.mugshot-image')[0]->src;
        $mugshots[] = [
            'avatar' => basename($mugshot_item_avatar),
            'name' => $mugshots_item->find('.card-title')[0]->innertext,
            'description' => $mugshots_item->find('.card-text')[0]->innertext,
            'date' => str_replace("Arrested:", "", $mugshots_item->find('.card-text')[1]->innertext),
            'demographics' => $mugshots_item->find('.table td')[0]->innertext,
            'location' => $mugshots_item->find('.table td')[1]->innertext,
            'agency' => $mugshots_item->find('.table td')[2]->innertext,
        ];
        if (!file_exists($wp_upload_dir['basedir'] . '/mugshots/' . basename($mugshot_item_avatar))) {
            $mugshots_ch = curl_init($mugshot_item_avatar);
            curl_setopt($mugshots_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($mugshots_ch, CURLOPT_REFERER, "https://jocoreport.com/mugshots/");
            curl_setopt($mugshots_ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36');
            $mugshot_item_avatar_data = curl_exec($mugshots_ch);
            if ($mugshot_item_avatar_data) {
                file_put_contents( $wp_upload_dir['basedir'] . '/mugshots/' . basename($mugshot_item_avatar), $mugshot_item_avatar_data );
            }
        }
    }
    file_put_contents( $wp_upload_dir['basedir'] . '/mugshots/mugshots.json', json_encode($mugshots) );

    $mugshots_last_7_days = [];
    $mugshots_last_7_days_html = file_get_html( 'https://jocoreport.com/7-day-mugshots/');
    $mugshots_last_7_days_items = $mugshots_last_7_days_html->find('.carousel-inner .carousel-item');
    foreach ($mugshots_last_7_days_items as $mugshots_item) {
        $mugshot_item_avatar = $mugshots_item->find('.mugshot-image')[0]->src;
        $mugshots_last_7_days[] = [
            'avatar' => basename($mugshot_item_avatar),
            'name' => $mugshots_item->find('.card-title')[0]->innertext,
            'description' => $mugshots_item->find('.card-text')[0]->innertext,
            'date' => str_replace("Arrested:", "", $mugshots_item->find('.card-text')[1]->innertext),
            'demographics' => $mugshots_item->find('.table td')[0]->innertext,
            'location' => $mugshots_item->find('.table td')[1]->innertext,
            'agency' => $mugshots_item->find('.table td')[2]->innertext,
        ];
        if (!file_exists($wp_upload_dir['basedir'] . '/mugshots/' . basename($mugshot_item_avatar))) {
            $mugshots_ch = curl_init($mugshot_item_avatar);
            curl_setopt($mugshots_ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($mugshots_ch, CURLOPT_REFERER, "https://jocoreport.com/mugshots/7-day-mugshots/");
            curl_setopt($mugshots_ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36');
            $mugshot_item_avatar_data = curl_exec($mugshots_ch);
            if ($mugshot_item_avatar_data) {
                file_put_contents( $wp_upload_dir['basedir'] . '/mugshots/' . basename($mugshot_item_avatar), $mugshot_item_avatar_data );
            }
        }
    }
    file_put_contents( $wp_upload_dir['basedir'] . '/mugshots/mugshots_last_7_days.json', json_encode($mugshots_last_7_days) );
}
add_action( 'mugshot_cron_hook', 'mugshot_cron_job_function' );