# Task List

WP Genius 插件任务清单

- [OK] SMART AUI 跳过删除文章时抓图的动作，直接删除文章到回收站。
- [ ] 菜单顺序调整
<aside>
始终将System Health菜单放在最底部，新增加的功能模组菜单放在它之前。
</aside>
- [ ] Block External HTTP (DANGER!) 启用时弹窗提醒
<aside>
在启用该功能时需要弹出窗口来提醒该设置项可能导致WordPress无法安装、更新主题、插件、核心等。
</aside>
- [ ] Media Trubo模组功能修复
<aside>
修复该功能模组的功能：
1、按媒体扫描
2、按文章扫描
扫描完成后执行压缩处理，处理完成后，检查文章中的图片是否被正确替换为新的媒体URL。
</aside>
- [ ] Post Duplicator模组功能修复
- [ ] SMTP Email底部按钮间距
- [ ] SMTP Email底部按钮提醒消息为空
- [ ] Cleanup Tools 的清理按钮统一样式
- [ ] Watermark设置页面样式匹配
- [ ] 前端增强模组中的 Audio Player 功能编写和上线
- [ ] 






# WP CLI 命令
# 列出指定类型和条件的文章数量
wp post list \
  --post_type=chapter \
  --meta_query='[{"key":"related_novel_id","value":"794449","compare":"="}]' \
  --format=ids | xargs -n1 echo | wc -l
  # 或者只统计数据
  --format=count


# 删除指定类型和条件的文章
wp post delete $(wp post list --post_type=chapter \
  --meta_query='[{"key":"related_novel_id","value":"804303","compare":"="}]' \
  --format=ids) --force

# 清理缓存
wp transient delete --all

# PHP-FPM 进程数
[10-Jan-2026 20:58:20] WARNING: [pool www] seems busy (you may need to increase pm.start_servers, or pm.min/max_spare_servers), spawning 8 children, there are 0 idle, and 22 total children