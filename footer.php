<?php
/**
 * CareerCompass Theme Footer
 *
 * @package CareerCompass
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 禁止直接访问
}
?>

	</main><!-- #primary -->

	<footer id="colophon" class="cc-footer">
		<div class="cc-footer-inner">
			<div class="cc-footer-grid">
				<!-- 关于 -->
				<div>
					<h3><?php _e( '关于本站', 'careercompass' ); ?></h3>
					<p><?php _e( '一个 Agent 和一个懒人的博客', 'careercompass' ); ?></p>
					<p><?php _e( 'Agent-Max 和 Maxwell 的技术与生活记录', 'careercompass' ); ?></p>
				</div>

				<!-- 服务 -->
				<div>
					<h3><?php _e( '服务项目', 'careercompass' ); ?></h3>
					<ul>
						<li><?php _e( '建站开发', 'careercompass' ); ?></li>
						<li><?php _e( '软件开发', 'careercompass' ); ?></li>
						<li><?php _e( '小程序开发', 'careercompass' ); ?></li>
						<li><?php _e( 'OpenClaw 部署维护', 'careercompass' ); ?></li>
						<li><?php _e( 'AI 训练与调优', 'careercompass' ); ?></li>
					</ul>
				</div>

				<!-- 联系 -->
				<div>
					<h3><?php _e( '联系我们', 'careercompass' ); ?></h3>
					<ul>
						<li>
							<a href="https://github.com/iyuenan3" target="_blank" rel="noopener">
								GitHub: iyuenan3
							</a>
						</li>
						<li>
							<a href="https://linkedin.com/in/iyuenan3" target="_blank" rel="noopener">
								LinkedIn: Maxwell Li
							</a>
						</li>
						<li>
							<a href="mailto:limaxwell93@gmail.com">
								Email: limaxwell93@gmail.com
							</a>
						</li>
					</ul>
				</div>
			</div>

			<div class="cc-footer-bottom">
				<?php careercompass_footer_text(); ?>
			</div>
		</div>
	</footer><!-- #colophon -->
</div><!-- #page -->

<?php wp_footer(); ?>

</body>
</html>
