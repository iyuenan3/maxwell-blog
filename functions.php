<?php
/**
 * CareerCompass Theme Functions
 *
 * @package CareerCompass
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 禁止直接访问
}

/**
 * 设置主题支持功能
 */
function careercompass_setup() {
	// 添加默认文章缩略图支持
	add_theme_support( 'post-thumbnails' );

	// 添加 RSS Feed 链接
	add_theme_support( 'automatic-feed-links' );

	// 添加标题标签支持（HTML5）
	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
	) );

	// 添加自定义 Logo 支持
	add_theme_support( 'custom-logo', array(
		'height'      => 96,
		'width'       => 384,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	// 添加自定义背景支持
	add_theme_support( 'custom-background', array(
		'default-color' => 'f8f9fd',
	) );

	// 添加标题支持
	add_theme_support( 'title-tag' );

	// 注册导航菜单
	register_nav_menus( array(
		'primary' => __( '主菜单', 'careercompass' ),
		'footer'  => __( '页脚菜单', 'careercompass' ),
	) );

	// 设置内容宽度
	if ( ! isset( $content_width ) ) {
		$content_width = 800;
	}
}
add_action( 'after_setup_theme', 'careercompass_setup' );

/**
 * 注册侧边栏
 */
function careercompass_widgets_init() {
	register_sidebar( array(
		'name'          => __( '主侧边栏', 'careercompass' ),
		'id'            => 'sidebar-1',
		'description'   => __( '添加到文章和页面右侧的小工具', 'careercompass' ),
		'before_widget' => '<section id="%1$s" class="widget %2$s">',
		'after_widget'  => '</section>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );

	register_sidebar( array(
		'name'          => __( '页脚区域 1', 'careercompass' ),
		'id'            => 'footer-1',
		'description'   => __( '页脚第一列', 'careercompass' ),
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );

	register_sidebar( array(
		'name'          => __( '页脚区域 2', 'careercompass' ),
		'id'            => 'footer-2',
		'description'   => __( '页脚第二列', 'careercompass' ),
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );

	register_sidebar( array(
		'name'          => __( '页脚区域 3', 'careercompass' ),
		'id'            => 'footer-3',
		'description'   => __( '页脚第三列', 'careercompass' ),
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h3 class="widget-title">',
		'after_title'   => '</h3>',
	) );
}
add_action( 'widgets_init', 'careercompass_widgets_init' );

/**
 * 引入样式和脚本
 */
function careercompass_scripts() {
	// 主样式表
	wp_enqueue_style(
		'careercompass-style',
		get_stylesheet_uri(),
		array(),
		'1.0.0'
	);

	// 导航脚本
	wp_enqueue_script(
		'careercompass-navigation',
		get_template_directory_uri() . '/assets/js/navigation.js',
		array(),
		'1.0.0',
		true
	);

	// 评论回复脚本
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'careercompass_scripts' );

/**
 * 自定义 excerpt 长度
 */
function careercompass_excerpt_length( $length ) {
	return 40;
}
add_filter( 'excerpt_length', 'careercompass_excerpt_length', 999 );

/**
 * 自定义 excerpt 更多文本
 */
function careercompass_excerpt_more( $more ) {
	return '...';
}
add_filter( 'excerpt_more', 'careercompass_excerpt_more' );

/**
 * 添加自定义文章类型：服务
 */
function careercompass_register_service_post_type() {
	$labels = array(
		'name'                  => _x( '服务项目', 'Post Type General Name', 'careercompass' ),
		'singular_name'         => _x( '服务项目', 'Post Type Singular Name', 'careercompass' ),
		'menu_name'             => __( '服务项目', 'careercompass' ),
		'name_admin_bar'        => __( '服务项目', 'careercompass' ),
		'archives'              => __( '服务归档', 'careercompass' ),
		'attributes'            => __( '服务属性', 'careercompass' ),
		'parent_item_colon'     => __( '父级服务:', 'careercompass' ),
		'all_items'             => __( '所有服务', 'careercompass' ),
		'add_new_item'          => __( '添加新服务', 'careercompass' ),
		'add_new'               => __( '添加新', 'careercompass' ),
		'new_item'              => __( '新服务', 'careercompass' ),
		'edit_item'             => __( '编辑服务', 'careercompass' ),
		'update_item'           => __( '更新服务', 'careercompass' ),
		'view_item'             => __( '查看服务', 'careercompass' ),
		'view_items'            => __( '查看服务', 'careercompass' ),
		'search_items'          => __( '搜索服务', 'careercompass' ),
		'not_found'             => __( '未找到服务', 'careercompass' ),
		'not_found_in_trash'    => __( '回收站中未找到服务', 'careercompass' ),
	);

	$args = array(
		'label'                 => __( '服务项目', 'careercompass' ),
		'description'           => __( '展示提供的服务项目', 'careercompass' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'taxonomies'            => array( 'service_category' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-portfolio',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
		'show_in_rest'          => true,
	);

	register_post_type( 'service', $args );
}
add_action( 'init', 'careercompass_register_service_post_type', 0 );

/**
 * 注册服务分类法
 */
function careercompass_register_service_taxonomy() {
	$labels = array(
		'name'                       => _x( '服务分类', 'Taxonomy General Name', 'careercompass' ),
		'singular_name'              => _x( '服务分类', 'Taxonomy Singular Name', 'careercompass' ),
		'menu_name'                  => __( '服务分类', 'careercompass' ),
		'all_items'                  => __( '所有分类', 'careercompass' ),
		'parent_item'                => __( '父级分类', 'careercompass' ),
		'parent_item_colon'          => __( '父级分类:', 'careercompass' ),
		'new_item_name'              => __( '新分类名称', 'careercompass' ),
		'add_new_item'               => __( '添加新分类', 'careercompass' ),
		'edit_item'                  => __( '编辑分类', 'careercompass' ),
		'update_item'                => __( '更新分类', 'careercompass' ),
		'view_item'                  => __( '查看分类', 'careercompass' ),
		'separate_items_with_commas' => __( '用逗号分隔分类', 'careercompass' ),
		'add_or_remove_items'        => __( '添加或删除分类', 'careercompass' ),
		'choose_from_most_used'      => __( '从最常用的分类中选择', 'careercompass' ),
		'popular_items'              => __( '常用分类', 'careercompass' ),
		'search_items'               => __( '搜索分类', 'careercompass' ),
		'not_found'                  => __( '未找到分类', 'careercompass' ),
		'no_terms'                   => __( '没有分类', 'careercompass' ),
		'items_list'                 => __( '分类列表', 'careercompass' ),
		'items_list_navigation'      => __( '分类列表导航', 'careercompass' ),
	);

	$args = array(
		'labels'                     => $labels,
		'hierarchical'               => true,
		'public'                     => true,
		'show_ui'                    => true,
		'show_admin_column'          => true,
		'show_in_nav_menus'          => true,
		'show_tagcloud'              => false,
		'show_in_rest'               => true,
	);

	register_taxonomy( 'service_category', array( 'service' ), $args );
}
add_action( 'init', 'careercompass_register_service_taxonomy', 0 );

/**
 * 自定义页脚版权文本
 */
function careercompass_footer_text() {
	?>
	<p>Copyright &copy; <?php echo date( 'Y' ); ?> Agent-Max &amp; Maxwell's Blog | Powered by <a href="https://wordpress.org/" target="_blank" rel="noopener">WordPress</a></p>
	<?php
}

/**
 * 获取阅读时间
 */
function careercompass_reading_time( $post_id = null ) {
	if ( ! $post_id ) {
		$post_id = get_the_ID();
	}

	$content = get_post_field( 'post_content', $post_id );
	$word_count = str_word_count( strip_tags( $content ) );
	$reading_speed = 200; // 每分钟 200 字
	$reading_time = ceil( $word_count / $reading_speed );

	return sprintf(
		_n( '%d 分钟阅读', '%d 分钟阅读', $reading_time, 'careercompass' ),
		$reading_time
	);
}
