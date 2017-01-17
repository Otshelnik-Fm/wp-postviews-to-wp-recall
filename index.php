<?php
/*
    Интеграция WP-Recall с плагином "WP-PostViews"
    https://wordpress.org/plugins/wp-postviews/
*/


// проверка что не активирован WP-PostViews или доп публикаций
function wppv_wpr_check_activate(){
    if (!function_exists('the_views') || !function_exists('rcl_add_postlist_posts')){
        return false;
    }
}



// добавляем в постлист - шаблон posts-list.php
function wppv_wpr_add_in_to_postlist($content){

    if(wppv_wpr_check_activate() === false) return $content; // не активен WP-PostViews или доп публикаций

    global $post;

    $cnt = intval(get_post_meta($post->ID, 'views', true)); // получаем целое значение
    if($cnt){
        $content .= '<span class="wppv_wpr_total" title="Всего просмотров">';
            $content .= '<i class="fa fa-eye"></i>'.$cnt;
        $content .= '</span>';
    }
    return $content;
}
add_filter('content_postslist','wppv_wpr_add_in_to_postlist');



// инлайн стили. Функция сама очистит от пробелов, переносов и сожмёт в строку
function wppv_wpr_inline_styles($styles){

    if(!rcl_is_office()) return $styles; // отработает только в ЛК

    if(wppv_wpr_check_activate() === false) return $styles; // не активен WP-PostViews или доп публикаций - не выводим стили ниже

    $styles .= '
        .wppv_wpr_total {
            background-color: rgba(219, 219, 219, 0.6);
            float: right;
            font: 12px/1 Helvetica,serif,arial;
            margin: 0 4px;
            padding: 5px;
            white-space: nowrap;
        }
        .wppv_wpr_total .fa {
            margin: 0 5px 0 0;
        }
        #subtab-wppv_top {
            box-shadow: 0 0 1px 1px #ddd;
        }
        .wppv_line {
            color: #777;
            display: inline-block;
            padding: 5px 0;
            width: 100%;
        }
        .wppv_line:nth-child(2n) {
            background-color: rgba(237, 237, 237, 0.8);
        }
        .wppv_type {
            display: inline-block;
            padding: 0 0 0 5px;
            text-align: center;
            width: 28px;
        }
        .wppv_date {
            font-size: 12px;padding: 0 10px 0 5px;
        }
    ';
    return $styles;
}
add_filter('rcl_inline_styles','wppv_wpr_inline_styles',10);




// добавим в вкладку "Публикации" дочернюю "Самые просматриваемые"
function wppv_wpr_add_second_sub_tab(){

    if(wppv_wpr_check_activate() === false) return false; // не активен WP-PostViews или доп публикаций - не выводим вкладку

    $subtab = array(
        'id'=> 'wppv_top',
        'name'=> 'Самые просматриваемые',
        'icon' => 'fa-eye',
        'callback'=>array(
            'name'=>'wppv_wpr_top_views',
        )
    );
    rcl_add_sub_tab('publics',$subtab);
}
add_action('rcl_setup_tabs','wppv_wpr_add_second_sub_tab',11);


// обработчик вкладки "Самые просматриваемые"
function wppv_wpr_top_views($user_lk){
    global $wpdb;

    $sql = "SELECT t1.ID, t1.post_date, t1.post_title, t1.post_type, t2.meta_value "
            . "FROM $wpdb->posts AS t1 "
            . "INNER JOIN $wpdb->postmeta AS t2 "
            . "ON t1.ID = t2.post_id "
            . "WHERE "
                . "t1.post_author = ".$user_lk." "
                ."AND t2.meta_key LIKE 'views' "
                ."AND t1.post_status = 'publish' "
                ."AND t1.post_type  IN ('post', 'post-group', 'products') "
            . "ORDER BY "
                . "t2.meta_value DESC "
            . "LIMIT 0, 50";

	$results = $wpdb->get_results($sql, ARRAY_A);

    return wppv_wpr_html_output($results, $ttl = 'Всего просмотров');
}



// вывод контента
function wppv_wpr_html_output($results, $ttl){
    foreach($results as $result){
        $name = wppv_wpr_post_type_convert($result['post_type']);
        $out .= '<div class="wppv_line wppv_top">';
            $out .= '<span class="wppv_type">'.$name.'</span>';
            $out .= '<span class="wppv_date">'.mysql2date('Y-m-d', $result['post_date']).'</span>';
            $out .= '<span class="wppv_title">';
                $out .= '<a target="_blank" href="'.get_permalink($result['ID']).'">'.$result['post_title'].'</a>';
            $out .= '</span>';
            $out .= '<span class="wppv_views wppv_wpr_total" title="'.$ttl.'">';
                $out .= '<i class="fa fa-eye"></i>'.$result['meta_value'];
            $out .= '</span>';
        $out .= '</div>';
    }
    return $out;
}



// конверт типа записи в иконку
function wppv_wpr_post_type_convert($type){
    switch($type){
        case 'post':
            $out = '<i class="fa fa-pencil" title="Публикация"></i>';
            break;
        case 'post-group':
            $out = '<i class="fa fa-users" title="Публикация в группе"></i>';
            break;
        case 'products':
            $out = '<i class="fa fa-shopping-cart" title="Публикация в магазине"></i>';
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

