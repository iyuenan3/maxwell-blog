<?php
/**
 * CareerCompass Theme Page Template
 *
 * @package CareerCompass
 * @since 1.0.0
 */

get_header();
?>

<div class="cc-container" style="max-width: 800px; padding: var(--cc-spacing-12) var(--cc-spacing-6);">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header cc-mb-8">
				<h1 class="entry-title cc-text-gradient" style="font-size: var(--cc-font-size-4xl);">
					<?php the_title(); ?>
				</h1>
			</header>

			<div class="entry-content" style="line-height: var(--cc-line-height-relaxed);">
				<?php
				the_content();

				wp_link_pages(
					array(
						'before' => '<div class="page-links">' . __( '分页：', 'careercompass' ),
						'after'  => '</div>',
					)
				);
				?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</div>

<?php
get_footer();
