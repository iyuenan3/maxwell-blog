<?php
/**
 * CareerCompass Theme Index
 *
 * @package CareerCompass
 * @since 1.0.0
 */

get_header();
?>

<div class="cc-container">
	<header class="page-header cc-mb-8 cc-mt-8">
		<h1 class="page-title cc-text-center cc-text-gradient">
			<?php
			if ( is_home() ) :
				_e( '全部文章', 'careercompass' );
			elseif ( is_category() ) :
				single_cat_title();
			elseif ( is_tag() ) :
				single_tag_title();
			elseif ( is_author() ) :
				the_author();
			elseif ( is_day() ) :
				printf( __( '%s 的文章', 'careercompass' ), get_the_date() );
			elseif ( is_month() ) :
				printf( __( '%s 的文章', 'careercompass' ), get_the_date( 'F Y' ) );
			elseif ( is_year() ) :
				printf( __( '%s 的文章', 'careercompass' ), get_the_date( 'Y' ) );
			else :
				_e( '文章归档', 'careercompass' );
			endif;
			?>
		</h1>
	</header>

	<div class="cc-posts-grid">
		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
				?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'cc-post-card' ); ?>>
					<?php if ( has_category() ) : ?>
						<div class="cc-mb-4">
							<?php
							$categories = get_the_category();
							if ( ! empty( $categories ) ) :
								echo '<span class="cc-tag">' . esc_html( $categories[0]->name ) . '</span>';
							endif;
							?>
						</div>
					<?php endif; ?>

					<h3 class="cc-post-card-title">
						<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
					</h3>

					<div class="cc-post-card-excerpt">
						<?php echo wp_trim_words( get_the_excerpt(), 20 ); ?>
					</div>

					<div class="cc-post-card-meta">
						<span><?php echo get_the_date(); ?></span>
						<span><?php echo careercompass_reading_time(); ?></span>
					</div>
				</article>
				<?php
			endwhile;

			the_posts_pagination(
				array(
					'mid_size'  => 2,
					'prev_text' => __( '上一页', 'careercompass' ),
					'next_text' => __( '下一页', 'careercompass' ),
				)
			);
		else :
			?>
			<div class="cc-text-center">
				<p><?php _e( '暂无文章', 'careercompass' ); ?></p>
			</div>
			<?php
		endif;
		?>
	</div>
</div>

<?php
get_footer();
