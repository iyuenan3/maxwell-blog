<?php
/**
 * CareerCompass Theme Header
 *
 * @package CareerCompass
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 禁止直接访问
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<link rel="icon" type="image/png" href="<?php echo get_template_directory_uri(); ?>/assets/images/favicon.png">
	<link rel="apple-touch-icon" href="<?php echo get_template_directory_uri(); ?>/assets/images/favicon.png">

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php _e( '跳转到内容', 'careercompass' ); ?></a>

	<header id="masthead" class="cc-navbar">
		<div class="cc-navbar-inner">
			<!-- Logo -->
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cc-navbar-logo" rel="home">
				<?php if ( has_custom_logo() ) : ?>
					<?php the_custom_logo(); ?>
				<?php else : ?>
					<img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo2.png" alt="<?php bloginfo( 'name' ); ?>">
					<span><?php bloginfo( 'name' ); ?></span>
				<?php endif; ?>
			</a>

			<!-- 导航菜单 -->
			<nav id="site-navigation" class="main-navigation">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'menu_id'        => 'primary-menu',
						'menu_class'     => 'cc-navbar-menu',
						'container'      => false,
						'depth'          => 1,
					)
				);
				?>
			</nav><!-- #site-navigation -->

			<!-- 移动端菜单按钮 -->
			<button class="mobile-menu-toggle" aria-label="<?php esc_attr_e( '切换菜单', 'careercompass' ); ?>">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M3 12h18M3 6h18M3 18h18"/>
				</svg>
			</button>
		</div>
	</header><!-- #masthead -->

	<main id="primary" class="site-main">
