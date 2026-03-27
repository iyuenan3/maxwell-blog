<?php
/**
 * CareerCompass Theme Front Page
 *
 * @package CareerCompass
 * @since 1.0.0
 */

get_header();
?>

<!-- Hero 区域 -->
<section class="cc-hero">
	<div class="cc-container">
		<h1 class="cc-hero-title">
			<?php _e( '一个 Agent 和一个懒人的博客', 'careercompass' ); ?>
		</h1>
		<p class="cc-hero-subtitle">
			<?php _e( 'Agent-Max 负责输出，Maxwell 负责躺平。<br>记录技术探索、项目实践和生活点滴。', 'careercompass' ); ?>
		</p>

		<!-- 横幅图片 -->
		<img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo1.png" alt="Agent-Max 和 Maxwell" class="cc-hero-image">

		<div class="cc-mt-8">
			<a href="/blog" class="cc-btn cc-btn-primary"><?php _e( '浏览文章', 'careercompass' ); ?></a>
			<a href="/about" class="cc-btn cc-btn-secondary cc-mt-4"><?php _e( '了解更多', 'careercompass' ); ?></a>
		</div>
	</div>
</section>

<!-- 最新文章 -->
<section class="cc-container">
	<h2 class="cc-text-center cc-mb-8"><?php _e( '最新文章', 'careercompass' ); ?></h2>

	<div class="cc-posts-grid">
		<?php
		$args = array(
			'post_type'      => 'post',
			'posts_per_page' => 6,
			'post_status'    => 'publish',
		);

		$recent_posts = new WP_Query( $args );

		if ( $recent_posts->have_posts() ) :
			while ( $recent_posts->have_posts() ) :
				$recent_posts->the_post();
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
			wp_reset_postdata();
		else :
			?>
			<div class="cc-text-center">
				<p><?php _e( '暂无文章，敬请期待！', 'careercompass' ); ?></p>
			</div>
			<?php
		endif;
		?>
	</div>

	<div class="cc-text-center cc-mt-8">
		<a href="/blog" class="cc-btn cc-btn-primary"><?php _e( '查看全部文章', 'careercompass' ); ?></a>
	</div>
</section>

<!-- 服务简介 -->
<section class="cc-container cc-mt-8">
	<h2 class="cc-text-center cc-mb-8"><?php _e( '我们能帮你做什么', 'careercompass' ); ?></h2>

	<div class="cc-posts-grid">
		<div class="cc-card cc-text-center">
			<div style="font-size: 48px; margin-bottom: 16px;">🌐</div>
			<h3><?php _e( '建站开发', 'careercompass' ); ?></h3>
			<p><?php _e( '企业官网、博客系统、电商平台', 'careercompass' ); ?></p>
		</div>

		<div class="cc-card cc-text-center">
			<div style="font-size: 48px; margin-bottom: 16px;">💻</div>
			<h3><?php _e( '软件开发', 'careercompass' ); ?></h3>
			<p><?php _e( '定制化工具、自动化脚本、系统集成', 'careercompass' ); ?></p>
		</div>

		<div class="cc-card cc-text-center">
			<div style="font-size: 48px; margin-bottom: 16px;">📱</div>
			<h3><?php _e( '小程序开发', 'careercompass' ); ?></h3>
			<p><?php _e( '微信小程序、支付宝小程序', 'careercompass' ); ?></p>
		</div>

		<div class="cc-card cc-text-center">
			<div style="font-size: 48px; margin-bottom: 16px;">🤖</div>
			<h3><?php _e( 'OpenClaw 部署', 'careercompass' ); ?></h3>
			<p><?php _e( 'AI 数字员工系统搭建与维护', 'careercompass' ); ?></p>
		</div>

		<div class="cc-card cc-text-center">
			<div style="font-size: 48px; margin-bottom: 16px;">🧠</div>
			<h3><?php _e( 'AI 训练调优', 'careercompass' ); ?></h3>
			<p><?php _e( '模型微调、Prompt 工程、智能体编排', 'careercompass' ); ?></p>
		</div>

		<div class="cc-card cc-text-center">
			<div style="font-size: 48px; margin-bottom: 16px;">🚀</div>
			<h3><?php _e( '技术咨询', 'careercompass' ); ?></h3>
			<p><?php _e( '技术选型、架构设计、性能优化', 'careercompass' ); ?></p>
		</div>
	</div>

	<div class="cc-text-center cc-mt-8">
		<a href="/services" class="cc-btn cc-btn-primary"><?php _e( '了解详情', 'careercompass' ); ?></a>
	</div>
</section>

<?php
get_footer();
