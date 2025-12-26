<!-- Smart Auto Upload Images Progress UI Template -->
<div id="w2p-smart-aui-backdrop" style="display: none;">
	<div id="w2p-smart-aui-progress-container">
		<div class="w2p-smart-aui-header">
			<h3>🚀 正在上传外部图片</h3>
			<button type="button" id="w2p-smart-aui-close-btn" class="w2p-smart-aui-close">×</button>
		</div>

		<div class="w2p-smart-aui-body">
			<!-- Preview Area -->
			<div class="w2p-smart-aui-preview-area">
				<div class="w2p-smart-aui-preview-box">
					<img id="w2p-smart-aui-preview-img" src="" alt="Preview">
					<div class="w2p-smart-aui-preview-placeholder">
						<span class="dashicons dashicons-format-image"></span>
					</div>
				</div>
				<div class="w2p-smart-aui-current-image">
					<div class="w2p-smart-aui-current-label">当前处理:</div>
					<div class="w2p-smart-aui-current-url" id="w2p-smart-aui-current-url">-</div>
				</div>
			</div>

			<!-- Progress Area -->
			<div class="w2p-smart-aui-progress-area">
				<div class="w2p-smart-aui-status-text">准备处理...</div>
				
				<div class="w2p-smart-aui-progress-bar">
					<div class="w2p-smart-aui-progress-fill" style="width: 0%"></div>
				</div>

				<div class="w2p-smart-aui-stats">
					<div class="w2p-smart-aui-stat">
						<span class="w2p-smart-aui-stat-label">总计:</span>
						<span class="w2p-smart-aui-stat-value" id="w2p-smart-aui-total">0</span>
					</div>
					<div class="w2p-smart-aui-stat success">
						<span class="w2p-smart-aui-stat-label">成功:</span>
						<span class="w2p-smart-aui-stat-value" id="w2p-smart-aui-success">0</span>
					</div>
					<div class="w2p-smart-aui-stat failed">
						<span class="w2p-smart-aui-stat-label">失败:</span>
						<span class="w2p-smart-aui-stat-value" id="w2p-smart-aui-failed">0</span>
					</div>
				</div>
			</div>
		</div>

		<div class="w2p-smart-aui-footer">
			<button type="button" id="w2p-smart-aui-cancel-btn" class="button">取消</button>
		</div>
	</div>
</div>

<style>
#w2p-smart-aui-backdrop {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.7);
	z-index: 999999;
	display: flex;
	align-items: center;
	justify-content: center;
}

#w2p-smart-aui-progress-container {
	background: #fff;
	border-radius: 8px;
	box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
	width: 90%;
	max-width: 700px;
	max-height: 90vh;
	overflow: hidden;
	display: flex;
	flex-direction: column;
}

.w2p-smart-aui-header {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	padding: 20px;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.w2p-smart-aui-header h3 {
	margin: 0;
	font-size: 18px;
	font-weight: 600;
}

.w2p-smart-aui-close {
	background: rgba(255, 255, 255, 0.2);
	border: none;
	color: #fff;
	font-size: 24px;
	width: 32px;
	height: 32px;
	border-radius: 50%;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	transition: all 0.2s;
}

.w2p-smart-aui-close:hover {
	background: rgba(255, 255, 255, 0.3);
	transform: rotate(90deg);
}

.w2p-smart-aui-body {
	padding: 30px;
	flex: 1;
	overflow-y: auto;
	display: grid;
	grid-template-columns: 200px 1fr;
	gap: 30px;
}

/* Preview Styles */
.w2p-smart-aui-preview-box {
	width: 100%;
	height: 150px;
	background: #f0f0f1;
	border-radius: 6px;
	overflow: hidden;
	position: relative;
	border: 2px solid #e5e5e5;
	display: flex;
	align-items: center;
	justify-content: center;
}

#w2p-smart-aui-preview-img {
	max-width: 100%;
	max-height: 100%;
	object-fit: contain;
	display: none;
	z-index: 2;
}

.w2p-smart-aui-preview-placeholder {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	color: #a7aaad;
}

.w2p-smart-aui-preview-placeholder .dashicons {
	font-size: 40px;
	width: 40px;
	height: 40px;
}

/* Progress Area Styles */
.w2p-smart-aui-status-text {
	font-size: 14px;
	color: #666;
	margin-bottom: 15px;
}

.w2p-smart-aui-progress-bar {
	height: 8px;
	background: #e0e0e0;
	border-radius: 4px;
	overflow: hidden;
	margin-bottom: 20px;
}

.w2p-smart-aui-progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
	transition: width 0.3s ease;
	border-radius: 4px;
}

.w2p-smart-aui-stats {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 15px;
	margin-bottom: 20px;
}

.w2p-smart-aui-stat {
	background: #f5f5f5;
	padding: 15px;
	border-radius: 6px;
	text-align: center;
	border-left: 4px solid #999;
}

.w2p-smart-aui-stat.success {
	border-left-color: #00a32a;
}

.w2p-smart-aui-stat.failed {
	border-left-color: #d63638;
}

.w2p-smart-aui-stat-label {
	display: block;
	font-size: 12px;
	color: #666;
	margin-bottom: 5px;
}

.w2p-smart-aui-stat-value {
	display: block;
	font-size: 24px;
	font-weight: 600;
	color: #1d2327;
}

.w2p-smart-aui-current-image {
	background: #f0f6fc;
	border-left: 4px solid #0073aa;
	padding: 10px;
	border-radius: 4px;
	margin-top: 10px;
}

.w2p-smart-aui-current-label {
	font-size: 11px;
	color: #666;
	margin-bottom: 3px;
	font-weight: 600;
}

.w2p-smart-aui-current-url {
	font-size: 12px;
	color: #0073aa;
	word-break: break-all;
	font-family: monospace;
	max-height: 3em;
	overflow: hidden;
	text-overflow: ellipsis;
}

.w2p-smart-aui-footer {
	padding: 15px 20px;
	background: #f5f5f5;
	border-top: 1px solid #ddd;
	display: flex;
	justify-content: flex-end;
}

#w2p-smart-aui-cancel-btn {
	background: #d63638;
	color: #fff;
	border: none;
	padding: 8px 20px;
	border-radius: 4px;
	cursor: pointer;
	transition: all 0.2s;
}

#w2p-smart-aui-cancel-btn:hover {
	background: #b32d2e;
}

@keyframes pulse {
	0%, 100% {
		opacity: 1;
	}
	50% {
		opacity: 0.5;
	}
}

.w2p-smart-aui-status-text.processing {
	animation: pulse 1.5s ease-in-out infinite;
}

@media (max-width: 600px) {
	.w2p-smart-aui-body {
		grid-template-columns: 1fr;
	}
	
	.w2p-smart-aui-preview-box {
		height: 120px;
	}
}
</style>
