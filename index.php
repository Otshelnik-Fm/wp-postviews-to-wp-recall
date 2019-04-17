<?php

/*
  Интеграция WP-Recall с плагином "WP-PostViews"
  https://wordpress.org/plugins/wp-postviews/
 *
 *
  ╔═╗╔╦╗╔═╗╔╦╗
  ║ ║ ║ ╠╣ ║║║ https://otshelnik-fm.ru
  ╚═╝ ╩ ╚  ╩ ╩

 */

if ( ! defined( 'ABSPATH' ) )
    exit;

/* проверка что не активирован WP-PostViews или доп публикаций */
function wppv_wpr_check_activate() {
    if ( ! function_exists( 'the_views' ) || ! function_exists( 'rcl_get_postslist' ) ) {
        return false;
    }
}

// добавляем в постлист - шаблон posts-list.php
add_filter( 'content_postslist', 'wppv_wpr_add_in_to_postlist' );
function wppv_wpr_add_in_to_postlist( $content ) {

    if ( wppv_wpr_check_activate() === false )
        return $content; // не активен WP-PostViews или доп публикаций

    global $post;

    $cnt = intval( get_post_meta( $post->ID, 'views', true ) ); // получаем целое значение
    if ( $cnt ) {
        $content .= '<span class="pv_wpr_total" title="Всего просмотров">';
        $content .= '<i class="rcli fa-eye"></i>';
        $content .= '<span>' . $cnt . '</span>';
        $content .= '</span>';
    }
    return $content;
}

// инлайн стили. Функция сама очистит от пробелов, переносов и сожмёт в строку
add_filter( 'rcl_inline_styles', 'wppv_wpr_inline_styles', 10 );
function wppv_wpr_inline_styles( $styles ) {

    if ( ! rcl_is_office() )
        return $styles; // отработает только в ЛК

    if ( wppv_wpr_check_activate() === false )
        return $styles; // не активен WP-PostViews или доп публикаций - не выводим стили ниже

    $styles .= '
        #pv_wpr_table a:hover {
            text-decoration: underline;
        }
        .pv_wpr_total {
            align-items: center;
            display: flex;
            white-space: nowrap;
        }
        .pv_wpr_total .rcli {
            color: #2eae5c;
            font-size: 1em;
            margin: 0 6px 0 0;
        }
        .pv_wpr_type {
            align-self: center;
            color: #999;
        }
        .rcl_author_postlist .rcl-table__row:not(.rcl-table__row-header) > div:nth-child(2) a {
            flex-grow: 1;
        }
        .rcl_author_postlist .rcl-table__row:not(.rcl-table__row-header) > div:nth-child(2) .rating-rcl {
            order: 1;
        }
    ';

    return $styles;
}

// добавим в вкладку "Публикации" дочернюю "Самые просматриваемые"
add_action( 'rcl_setup_tabs', 'wppv_wpr_add_second_sub_tab', 11 );
function wppv_wpr_add_second_sub_tab() {

    if ( wppv_wpr_check_activate() === false )
        return false; // не активен WP-PostViews или доп публикаций - не выводим вкладку

    $subtab = array(
        'id'       => 'wppv_top',
        'name'     => 'Самые просматриваемые',
        'icon'     => 'fa-eye',
        'callback' => array(
            'name' => 'wppv_wpr_top_views',
        )
    );
    rcl_add_sub_tab( 'publics', $subtab );
}

// обработчик вкладки "Самые просматриваемые"
function wppv_wpr_top_views( $user_lk ) {
    global $wpdb;

    $sql = "SELECT t1.ID, t1.post_date, t1.post_title, t1.post_type, t2.meta_value "
        . "FROM $wpdb->posts AS t1 "
        . "INNER JOIN $wpdb->postmeta AS t2 "
        . "ON t1.ID = t2.post_id "
        . "WHERE "
        . "t1.post_author = " . $user_lk . " "
        . "AND t2.meta_key LIKE 'views' "
        . "AND t1.post_status = 'publish' "
        . "AND t1.post_type  IN ('post', 'post-group', 'products') "
        . "ORDER BY "
        . "t2.meta_value DESC "
        . "LIMIT 0, 50";

    $results = $wpdb->get_results( $sql, ARRAY_A );

    return wppv_wpr_html_output( $results, $ttl = 'Всего просмотров' );
}

function wppv_notice_box( $text, $type = 'success' ) {
    return '<div class="notify-lk"><div class="' . $type . '">' . $text . '</div></div>';
}

// вывод контента
function wppv_wpr_html_output( $results, $ttl ) {
    if ( ! $results )
        return wppv_notice_box( 'Пока просмотров нет' );

    $Table = new Rcl_Table( array(
        'cols'     => array(
            array(
                'align' => 'center',
                'title' => 'Тип',
                'width' => 5
            ),
            array(
                'align' => 'center',
                'title' => 'Дата',
                'width' => 15
            ),
            array(
                'title' => 'Заголовок',
                'width' => 65
            ),
            array(
                'align' => 'center',
                'title' => 'Просмотры',
                'width' => 15
            )
        ),
        'table_id' => 'pv_wpr_table',
        'zebra'    => true,
        'class'    => 'pv_wpr_views_table',
        'border'   => array( 'table', 'cols', 'rows' )
        ) );

    foreach ( $results as $result ) {
        $name    = wppv_wpr_post_type_convert( $result['post_type'] );
        $content = '<a target="_blank" href="' . get_permalink( $result['ID'] ) . '">' . $result['post_title'] . '</a>';
        $status  = '<span class="pv_wpr_views pv_wpr_total" title="' . $ttl . '">';
        $status  .= '<i class="rcli fa-eye"></i>';
        $status  .= '<span>' . $result['meta_value'] . '</span>';
        $status  .= '</span>';

        $Table->add_row( array(
            $name,
            mysql2date( 'Y-m-d', $result['post_date'] ),
            $content,
            $status
        ) );
    }

    return $Table->get_table();
}

// конверт типа записи в иконку
function wppv_wpr_post_type_convert( $type ) {
    switch ( $type ) {
        case 'post':
            $out = '<i class="rcli fa-pencil pv_wpr_type" title="Публикация"></i>';
            break;
        case 'post-group':
            $out = '<i class="rcli fa-users pv_wpr_type" title="Публикация в группе"></i>';
            break;
        case 'products':
            $out = '<i class="rcli fa-shopping-cart pv_wpr_type" title="Публикация в магазине"></i>';
            break;
    }
    return $out;
}

// прямые sql запросы для дебага
/*
// популярные за все время с автором
SELECT t1.ID, t1.post_date, t1.post_title, t1.post_type, t2.meta_value
FROM `wp_posts` AS t1
INNER JOIN `wp_postmeta` AS t2
ON t1.ID = t2.post_id
WHERE t1.post_author = 3
AND t2.meta_key LIKE 'views'
AND t1.post_type  IN ('post', 'post-group', 'products')

ORDER BY t2.meta_value DESC

LIMIT 0, 10
*/

