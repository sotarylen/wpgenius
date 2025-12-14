
    
	<div class="reader-toolbar">
	 	<button onclick="loadFiles()" ><?php _e('Start Loadfile', 'wp-genius'); ?></button>
		<button id="line-height-in"><?php _e('+ Line height', 'wp-genius'); ?></button> <!--增加行高-->
		<button id="zoom-in"><?php _e('+ Size', 'wp-genius'); ?></button> <!--增加字号-->
		<button id="zoom-out"><?php _e('- Size', 'wp-genius'); ?></button> <!--减少字号-->
		<button id="line-height-out"><?php _e('- Line height', 'wp-genius'); ?></button> <!--减少行高-->
        <button type="button" id="imagePicker"><?php _e('Background', 'wp-genius'); ?></button> <!--修改背景图片-->
		<input type="color" id="color-picker" class="reader-color-picker"> <!--修改背景颜色-->
		<button id="copyButton"><?php _e('Copy fileContent', 'wp-genius'); ?></button>
	</div>

	<div id="contentContainer">
		<div id="fileMenu">
			<h2 style="text-align: center;"><?php _e('MenuTitle', 'wp-genius'); ?></h2>
		</div>
		<div id="fileContent">
			<?php _e('Nothing to loading any file,please click "load file" button to load file or directory.', 'wp-genius'); ?>
		</div>
	</div>