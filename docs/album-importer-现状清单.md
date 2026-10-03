# album-importer 现状清单

> 生成时间：2026-10-02 20:5x ｜ 生成原因：老雷要求「先停下别改，给我一份现状清单」
> 本文件只陈述事实与账目，不含任何改动建议的承诺。

---

## 一、一句话结论

功能是通的，**但我在实现路径上绕了几个不该绕的弯**，而且五轮下来是「打补丁式推进」——每一步单看有理由，合起来就是没有退一步看整体。下面是完整账目。

---

## 二、五轮都改了什么

| 轮次 | 起因 | 改了什么 |
|---|---|---|
| 1 | 你最初的需求 | 新建 `album-importer` 模组：扫目录 → 解析目录名 → 待导入列表 → 建 `albums` + 图片入媒体库 |
| 2 | 你说「我选择哪个就是哪个」 | 把「设置页配一个固定扫描根」改成**目录下钻选择**；加层级自动判断；浏览根硬编码为 `/mnt/pictures` |
| 3 | 你说标签要能删、工作室要能编辑、日期要带点 | 模特标签加 `×`；工作室改成可编辑（带既有词条候选）；日期显示 `2019.12.20`／底库存 `20191220` |
| 4 | 你实测的 7 条反馈 | 提速、源图移入 `_已导入/`、补封面、模特「识别到就建」、加停止按钮、改进度口径、丢弃按钮、文件名加日期 |
| 5 | 你反馈「全显示失败」 | 查明是 FPM 池被打满（我第 4 轮开并发造成的）→ 退回单路；并按你的建议**把管道改成复用 smart-aui 抓图** |

---

## 三、现在的真实状态（实测数据，非估计）

### 数据库

| 项 | 数值 |
|---|---|
| 本模组导入的图集 | **107** 篇 |
| 其中未收尾（state ≠ done） | **1** 篇 |
| 有封面 | **107** 篇 |
| 源图已归档移动（`_已导入/`） | **1** 次 |
| 导入的图片总数 | **7061** 张 |
| 其中没有缩略图的附件 | **5** 张 |

> 注：7061 张里绝大多数**是有缩略图的**——那是我第 1～4 轮的旧管道产物。第 5 轮换成 smart-aui 后新导入的才没有缩略图（它刻意跳过缩略图生成）。

### 磁盘

| 项 | 状态 |
|---|---|
| `wp-content/uploads/albums/w2p-*`（我建的暂存目录） | **残留 1 个** ← 中断遗留，我的收尾没兜住 |
| 源目录里的 `_已导入/` | **1 个**：`Processing/YouWu尤物馆[8500p]174套/_已导入` |

### 代码与环境

| 项 | 状态 |
|---|---|
| `wpgenius` 的改动 | **只新增** `includes/modules/album-importer/`，**没有修改任何既有文件** |
| 模组规模 | 17 个文件；核心 `class-album-importer.php` **1614 行**、`album-importer.js` **974 行** |
| 环境改动 | `docker-compose.yml` 只多 1 行挂载（`:ro` 已改为可写） |
| 备份 | `docker-compose.yml.bak-pictures-20261002101318`（加挂载前）、`.bak-rwmount-20261002194534`（改可写前） |

---

## 四、我人为绕的弯（认账）

**① 中转目录：把源图先拷进 `uploads/albums/w2p-<id>/` 再让 smart-aui 读进去**
- 为什么这么写：smart-aui 的本地直读判断在源码里**写死了 6 处** `strpos($url, '/wp-content/uploads/albums/')`，URL 不落在那个路径下它就一律走 HTTP 抓取；而 `/mnt/pictures` 没有 HTTP 地址。
- **弯在哪**：源图本来就在本地，我却让同一份数据**搬了两次**（源 → 暂存 → 媒体库）。而且我**没照它现有的命名**（站上 5151 个现成目录是 `albums/<编号>/webp/`），自己发明了 `w2p-<id>/`。

**② 文件名日期后缀我自己也加了一遍**
- smart-aui 按 `%filename%-%post_date%` 自己会加，我又加了一遍，实测撞成 `a-1-2026-10-02-2026-10-02.webp`。已删掉自建的那段。

**③ 封面我自己 `set_post_thumbnail`，而 smart-aui 本来有 `auto_set_featured_image`**
- 你在反馈里就点出来了。它的问题是**在 AJAX 上下文里钩子会提前 return**，所以不能只靠"更新文章自动生效"，必须显式调——但这是可以做到的。

**④ 第 4 轮开并发是明确的判断失误**
- 我读配置时已经看到 `pm.max_children = 6` 和那条 OOM 告警注释，仍然开了 2 路并发，直接导致池子被打满、你看到"每行都失败"。

**⑤ 进度游标（`META_OFFSET`）我从头到尾没做完整**
- 它是用来做中断续跑的，但没有覆盖「中断后暂存残留」这条路径 —— 这就是磁盘上那个残留目录的来源。

---

## 五、回滚路径（三种粒度，都干净）

| 粒度 | 做法 | 影响 |
|---|---|---|
| **只退交互/某轮改动** | 逐项改回去（比如把管道切回「直读直写」） | 保留模块与已导入数据 |
| **退整个功能** | **删除 `includes/modules/album-importer/` 整个目录** | 模组消失，**其它文件一个都没动过**，站点不受影响 |
| **连环境一起退** | 上面的 ＋ 删掉 `docker-compose.yml` 里那 1 行挂载（或换回 `.bak-rwmount-20261002194534`），重建 php 容器 | 挂载恢复只读 |

**已导入的数据**：107 篇图集 + 7061 张图，可以用模块自带的「丢弃」按钮逐条清，也可以我写一次性脚本批量回收（会连 MinIO 上的对象一起删）。

---

## 六、待你定的三件事（我不擅自动手）

1. **图片管道走哪条** —— ① 回退到「源图读一次、直接落媒体库」（一次拷贝、有缩略图、自动卸载，约 1s/张）；② 保留 smart-aui 抓图（快、但不生成缩略图也不自动卸载），只把中转目录命名改成跟 n8n 一致的 `albums/<编号>/webp/`。
2. **眼下这 1 个残留暂存目录 + 1 篇未收尾** —— 要不要我清掉/补齐。
3. **模块的整体去留** —— 如果你觉得这轮把复杂度推得太高，删掉整个目录即可回到零影响状态；源图我一张都没删过（只移动过一次，在 `YouWu尤物馆[8500p]174套/_已导入` 下）。

---

## 附：我这边没有动过的东西（明确声明）

- 站上**既有 8473 篇图集**：我没有修改过任何一篇的内容、字段或词条关联。
- 你的**源图片文件**：只发生过 1 次移动（`YouWu尤物馆[8500p]174套` 下的某一套，移进了同级的 `_已导入/`），**从未删除过任何文件**。
- **其它插件/主题**：代码上零改动（`git status` 只有新增目录）。

---

## 七、L2 落地与本轮修复（2026-10-02 晚）

> 上面第六节的三问已定：**①走标准入库路径（L2）**；②残留与未收尾那篇**不动**；
> ③模块保留。本节记录落地后再修掉的东西。

### 7.1 L2 是什么

把图片管道从「复用 smart-aui 抓图」换回 **WP 标准入库**：
`copy()` → `wp_insert_attachment()` → `wp_generate_attachment_metadata()`。
理由：smart-aui 的 `add_to_media_library()` **刻意跳过** `wp_generate_attachment_metadata()`，
而 advmo 的自动卸载只挂在这个 filter 上 —— 跳过它既没有缩略图、也永远不会卸载到 MinIO，
与站上既有图集形态不符（实测：1,095,832 个附件里 240,247 个带 `-scaled`，标准路径的产物）。

### 7.2 修掉的三条根因

| # | 症状 | 根因 | 修法 |
|---|---|---|---|
| **A** | 第二/三批**重复内容** | 幂等键用**目标文件名**。它要过 `wp_unique_filename()`，而后者读磁盘现状 —— 重跑时上一轮文件还在，返回 `xxx-1.webp`，键就变了、查不中 → 重复入库。实测重现出同一张图三份：`a-1-…`、`a-1-…-1`、`a-1-…-1-1` | 幂等键改为**源图绝对路径**（`META_SRC_FILE`），与磁盘状态无关；写入时机提前到生成缩略图之前 |
| **B** | 排序/封面**不确定** | `import_one_image()` 没写 `menu_order`，全部为 0 → `orderby=menu_order` 退化成 MySQL 隐式顺序 | 写入篇内绝对序号（`$offset + $i`）。已查证站点既有 8473 篇的约定就是 **0 起递增** |
| **C** | UI **卡死** | jQuery `$.ajax` **默认不超时**；FPM 子进程被占满时请求挂在池里，前端永久停在 importing（导航被冻结） | 批次请求加 240s 客户端超时 + 退避重试（服务端已幂等，重发安全） |

附带清掉：`module.php` 里每批调一次的 `wp_cache_flush()`。
它是 `flushDB`，会打掉**整个共享 Redis**（波及其它后台请求）；而且一批图要跑 30~40s，
期间 Redis 连接被判闲置，这里正好是那之后的第一次缓存操作 → 每批稳定踩中
`RedisException: read error on connection`。请求末尾本来就会随进程回收，无需手清。

### 7.3 验证（三档）

> ⚠️ 本节用的自检脚本 `tests/run-import-check.php` 已于 2026-10-03 从代码库移除（见 8.8），下表为当时的留档结果。

| 档位 | 命令 | 结果 |
|---|---|---|
| 默认（已修） | `wp eval-file .../tests/run-import-check.php` | **77 / 77 PASS** |
| 已禁用（临时装回旧逻辑） | 同上 | **9 FAIL**，重复如实复现（`before 1 → after 2`，三份同名图） |
| 已还原 | 同上 | **77 / 77 PASS** |

`phpcs --standard=phpcs.xml.dist includes/modules/album-importer/` → **0 errors, 0 warnings**。

### 7.4 自检为什么上次没抓到

旧自检的幂等断言**是空转的**：整篇导完后源目录已被移进 `_已导入/`，
再调 `import_image_batch()` 会走「源目录不存在 → 早退收尾」那条捷径，一次入库都没跑。
现在改成**趁源目录还在**时重发同一批，并新增一条针对性断言：
人为在磁盘上放同名文件制造 `wp_unique_filename()` 碰撞，验证幂等键不受其影响。

### 7.5 未解决 / 留给下一轮

- **单张耗时**：实测 4.5MB 的 GIF 走完标准路径 **1750ms**（该图只生成 2 个尺寸）。
  真图更大 → 一批 5 张 30~40s。这是**真实工作量**（缩略图编码 + MinIO 上传），不是重试风暴。
  可选杠杆（本轮**未**动，需你拍板）：导入期间临时关掉 `big_image_size_threshold`，
  省掉大图那次整幅重编码，代价是新导入与站上 22% 的 `-scaled` 形态不一致。
- **进度条细粒度 + ETA**：`import_image_batch()` 已返回 `timing.elapsed_ms` / `per_image_ms`，UI 直接用即可。
- **存量 7061 张补齐**：按你的决定不做。

### 7.6 动过的文件

| 文件 | 改动 |
|---|---|
| `includes/class-album-importer.php` | 新增 `META_SRC_FILE`；`find_existing_attachment()` → `find_attachment_by_source()`；`import_one_image()` 重写（幂等键前置 + `menu_order`）；`import_image_batch()` 传序号；`finalize_album()` 兜底排序 |
| `module.php` | 移除 `wp_cache_flush()`；新增 `importTimeout` 文案 |
| `assets/js/album-importer.js` | `request()` 支持超时；批次请求 240s 超时 + 退避重试；`BATCH_RETRY` 1→2 |
| `tests/run-import-check.php`（**已移除**） | 幂等断言改为「趁源目录还在」；新增文件名碰撞、`menu_order`、源路径键三条断言 |

---

## 八、导入速度为什么是半小时（2026-10-03）

### 8.1 结论先讲

**不是图片处理慢，是参数名撞车。** 每批 5 张图真正的工作量只有 5~14s，
但第 2 批起每批会额外背上一场**全站重建索引**，53 万条 SQL、PHP 堆撞 **256M** 上限直接 fatal。
~100 张图 = 21 批，所以是「第一批正常，之后每批 60~95s」→ 半小时。

> **修正第七节 7.5 的判断。** 那里写「一批 5 张 30~40s 是真实工作量，不是重试风暴」——**错了**。
> 那 30~40s 里绝大部分是下面这个第三方索引器，不是编码和上传。

### 8.2 撞车点

| 侧 | 代码 | 读的是 |
|---|---|---|
| 本模组 | `module.php` 批次 handler | `$_POST['offset']` = 图片游标（**第二批起必然 > 0**） |
| us-core（Impreza） | `us-core/admin/functions/filter-indexer.php:596` | `$offset = (int) ( $_POST['offset'] ?? 0 );` |

`US_Filter_Indexer` 挂在 `save_post` 上（`enable_auto_filter_reindex` 为真时）。
它的 `index()` 第 599 行：`if ( 0 < $offset )` → 载入 option `us_filter_indexer_indexing`
（本站是 9 天前中断遗留的 **2.5MB / 35.6 万条 post ID 列表**）→ 从 `$offset` 开始**遍历全站文章重建索引**。

而我们的批次在第 4 步（写正文）调 `wp_update_post()` → 触发 `save_post` → 撞上它。
于是「我们只是追加几行 `<img>`」变成了「顺带把全站 35 万篇文章重建一遍索引」。

`$_POST['offset'] = 0`（第一批）时 `index()` 走的是 `is_int($post_id)` 分支，只索引这一篇 → 正常。
**这就是「第一批永远正常、后面每批都卡」的机制。**

### 8.3 实测证据

一次 5 张全新大图的批次（`wp-admin/admin-ajax.php` 真实 HTTPS 请求）：

| 指标 | 修复前 | 修复后 |
|---|---|---|
| `$_POST` 字段 | `offset=4644` | `{"offset": null, "w2p_offset": "1000"}` |
| 单批耗时 | 60~95s 后 fatal | **14.2s**（含 0.48s 引导） |
| SQL 条数 | **327,313** | **310** |
| 峰值内存(cgroup) | 46MB → 510MB → 崩（PHP 堆 256M fatal） | **86.5MB** |
| `wp_us_filter_index_temp` 上的 DELETE | 恰好 **50 次**（= us-core 的 `chunk_size`） | 0 |

补测：第二批（offset=1005，5 张新图）**5.3s / 304 条 SQL / 78.5MB**；
重发已导入的批次 → `generated: 0`、`advmo_files: 0`、769ms、9 条 SQL（幂等未被改坏）；
附件数 84 → 94 = 精确 +10，零重复。

### 8.4 修法

改 `$_POST['offset']` → `$_POST['w2p_offset']`（`module.php` + `album-importer.js` 两处）。

**为什么是改名，而不是在 `wp_update_post()` 前后摘掉 us-core 的钩子**：
摘钩子要按类名去操作第三方插件的全局状态、还得处理它挂在两个 action 上的两份回调，
而且以后 us-core 改了内部结构就静默失效。改名是**我们自己的接口约定**，
自洽、不依赖第三方实现、也不会顺手把该做的索引更新一起干掉。

⚠️ **这个字段名不许再改回 `offset`。** `module.php` 与 `album-importer.js` 里都留了原因注释，
原先 `run-import-check.php` 里有 4 条契约断言盯着它（脚本 2026-10-03 已移除，防回归改由 `module.php` / `album-importer.js` 里的醒目注释承担）。

### 8.5 分阶段验证（三档）

| 档位 | 命令 | 结果 |
|---|---|---|
| 默认（已修） | `wp eval-file .../tests/run-import-check.php` | **81 / 81 PASS** |
| 已禁用（装回裸 `offset`） | 同上 | **77 / 81，4 条契约断言 FAIL**（证明断言能判红） |
| 已还原 | 同上 | **81 / 81 PASS** |

`phpcs`（`module.php` + `run-import-check.php`）→ **0 errors, 0 warnings**；`php -l` / `node --check` 均通过。

> ⚠️ 同上：自检脚本已于 2026-10-03 移除（见 8.8），本节为留档。

### 8.6 顺手挖出来的另一个坑（**老雷批准，已执行清理**）

本站 us-core 的过滤器索引**卡死在中途**，状态已经坏了 9 天：

```
us_filter_indexer_transients = {"num_indexed":279000,"num_total":356281,"touch":9天前}
us_filter_indexer_indexing   = 2.5MB（35.6 万条 ID 列表，未清）
wp_us_filter_index_temp      = 13,282,478 行   ← 半成品，占了大量磁盘
wp_us_filter_index（生效）    = 16,814 行
```

两个后果：

1. **索引写错表**：`is_indexing()` 为真 → 索引写入走 **`_temp`** 表，**生效表不更新**。9 天来所有
   新发/修改的文章，过滤器索引都是 stale 的。
2. **它是颗雷**：任何带 `offset>0` 的 `save_post` 请求（不只是我们）都会引发 8.2 那场全站重建。
   它自己的续跑机制（`get_progress()` 发 `wp_remote_post` 到 admin-ajax）在本机被
   Clash fake-ip 的 loopback 问题挡住，所以 9 天来自愈不了，`touch` 一直没刷新。

顺带一提：全站只有 **1 条** `_us_faceted_filter_items` meta —— 这套过滤器基本没在用，
那 1328 万行大概率是纯垃圾。清理方案（**老雷 2026-10-03 批准「修！」并已执行**）：

```sql
-- 让它回到「未在重建」状态：索引用回生效表，续跑机制自动失效
UPDATE wp_options SET option_value='' WHERE option_name IN ('us_filter_indexer_indexing','us_filter_indexer_transients');
DROP TABLE wp_us_filter_index_temp;   -- 1328 万行半成品
```

**执行记录**（2026-10-03）：

- 备份先落盘：`/tmp/w2p-backup-us-core-20261003/`（`us_filter_indexer_indexing.json` 2.5MB /
  `us_filter_indexer_transients.json` / `temp_table_schema.sql`）。
- 两条 SQL **同一次执行、不可拆**。**只清 option 不删表会出事**：
  `get_progress()` 见 `is_indexing()` 转假，会去发 `resume_index` 的 `wp_remote_post`（带 `offset=279000`）；
  而 `index()` 拿到空串 `json_decode('')=null` → PHP 8 上 `null` 当数组用 → TypeError。
  **只删表不清 option 也会出事**：`is_indexing()` 仍为真 → 下次重建 `set_table('auto')` 指向 `_temp`，
  而 `create_table` 是 `CREATE TABLE ... LIKE`（**没有 `IF NOT EXISTS`**、返回值被忽略）→
  表已存在则静默失败 → 后续 `INSERT ... SELECT` 直接污染 17k 行的生效表。
- 执行结果：两个 option `LENGTH=0`；`wp_us_filter_index_temp` 已不存在（1328 万行 / 1.7GB，0.2s 删完）；
  生效表 `wp_us_filter_index` **2.8MB 完好保留**；`wp_us_filter_cache` 未动。
- 运行时校验：`is_indexing()=false`、`get_progress()=-1`、首页 200。

> 注：`us_filter_indexer_indexing` 的 autoload 是 `auto-off`，所以清它**不减少**自动加载体积，
> 与「加速模组」那条线没关系，纯粹是拆雷。

### 8.7 剩下的、不是 bug 的耗时

- 5 张图 5~14s（看单张大小），其中 **advmo 同步上传占 20~32%**（2.0~2.8s）——同步是必须的，
  正文 `<img>` 要用返回的 URL，没法延后。
- 其余是 `wp_generate_attachment_metadata()` 生成 4 个尺寸（thumbnail / medium / large / us_600_600）
  + 超大图的 `-scaled` 整幅重编码。站上尺寸约定就是这些，**不建议为提速改**。
- `DEFAULT_BATCH=5`：引导成本只占单批 0.48s/14s ≈ 3%，调大收益很小，反而更久占住共享的
  6 个 FPM 子进程。**维持 5**。

**提速后的量级：~100 张图 ≈ 2~5 分钟**（原 30 分钟）。

### 8.8 测试脚手架已从代码库移除（2026-10-03）

老雷要求清理。移除内容：

| 位置 | 内容 | 理由 |
|---|---|---|
| `includes/modules/album-importer/tests/`（整个目录，4 个文件） | `run-import-check.php` / `run-scan-check.php` / `run-self-test.php` / `index.php` | 全项目**只有这一个模块**有 `tests/`，是孤例；且**没有 `.distignore`**，会跟着发布包一起发出去 |
| `includes/class-album-name-parser.php` 第 128~302 行 | `self_test()` 方法（**176 行，占文件一半以上**） | 测试用例与断言机塞在生产类里，生产路径永不触达 —— 明确代码异味 |

- 移除前已 `cp -R` 备份至 `/tmp/w2p-album-tests-backup-20261003/`（临时目录，重启即失，需要长期留档请另行挪走）。
- 库里已无 `self_test` / `run-*-check` 任何引用；`php -l` 与 `phpcs` 均 0 报错。
- **代价**：`w2p_offset` 的自动回归断言没了，防回归只剩 `module.php` / `album-importer.js` 里的醒目注释。
  8.5 的三档验证结论仍成立，但**不可再复现**。

---

## 九、「导入时容器内存跑满」的结论（2026-10-03）

**不是内存泄漏，是可回收的 page cache + FPM 常驻堆顶着 1GiB cgroup 上限。**

老雷反馈「导入 14 套图，OrbStack 内存直线上升，整个容器基本跑满」。实测：

| 项 | 读数 |
|---|---|
| `php_wp` cgroup `memory.current` | 244MB = anon **99MB** + file(page cache) **139MB** + shmem **73MB** |
| `memory.peak` | **1073741824**（正好等于 1GiB 上限） |
| `memory.events` | `max 44651`（反复触顶进入 reclaim）、**`oom_kill 0`** |
| VM | `MemTotal 3.9GiB`、`MemFree 175MB`、**`MemAvailable 2.2GB`** |
| FPM 子进程 | 6 个，RSS 111/114/115/110MB |

判据：**`oom_kill = 0` 且 `MemAvailable` 富余** → 没有进程被杀、内存可回收，
所谓「跑满」是 cgroup 把 page cache 也算进去后触到 soft reclaim，体感卡顿真实存在，但不是泄漏。

> ⚠️ **别再用「6 个子进程 × RSS 140MB = 840MB」这种算法下结论。** RSS 会把共享库
> 与 shmem（OPcache 128MB 共享段只算一份）重复计进每个进程，是**严重高估**。
> 真实 anon 只有 192MB（6 worker）。要看内存就看 cgroup 的 `anon` / `file` / `shmem` 拆分。

**唯一保留的改动**：`pm.max_requests: 1000 → 100`（`nginx/php/zz-custom-fpm.conf`）。
这是唯一能真正「把某个 worker 历史上涨上去的 Zend 堆还回去」的杠杆（Zend MM 不归还堆给 OS，
只能靠 worker 回收）；OPcache 是共享段，回收代价很低。

> ⚠️ **别再调小 FPM 池子。** 当天曾把 `pm.max_children` 从 6 降到 4，结果后台导入期间
> admin-ajax 请求排队到 **28s**、首页 22~49s；改回 6（只动这一个变量）后心跳 **0.25s**、首页 **0.48s**。
> 结论：6 个 worker 是够用的底线，池子小了长请求会占满 worker 让页面排队。**已回滚并留注释**。

顺带：那 14 套图 13.5 分钟内跑完 ~740 张 / 148 批 ≈ **5.5s/批**，佐证 8.1 的 `w2p_offset` 修复已生效。

---

## 十、待导入列表列宽（2026-10-03）

**诉求**：列宽自适应，仅固定 Directory 宽度（原先 Directory 被压成 3 行折行）。

**根因**：`.w2p-album-table` 无 `table-layout` → 默认 auto 布局；`.w2p-album-dirname` 带
`word-break: break-all`，把该列的 min-content 宽压到约 1 个字符；相邻 input 的 `min-width`
（90 / 130px）是硬下限；于是自适应算法把全部挤压都倒给 Directory。而 `max-width` 加在
`table-cell` 上在 auto 布局下**不是硬约束**。

**方案**：保持 auto 布局；Directory 靠**单元格内 `display:block` 元素**的 `max-width:280px`
夹住（block 的 max-width 在 auto 表格布局下会被如实计入该列内容宽）；表头加 `class="w2p-album-col-dir"`
配 `width:280px`；`.w2p-album-dirname` 加 `nowrap/hidden/ellipsis`；JS 补 `title` 悬停看全名。

> ⚠️ **`table-layout: fixed` 是错的，别再加回来。** 真实 Chrome 实测：fixed 下「未声明宽度」的列
> 只能分剩余空间，Serial/Release/Images/Status 这些固定列会吃掉 1000px+，把 Title/Studio/Model(s)
> 挤到 95px（容器 1040px 时只剩 29px，820px 时 0px + 横向溢出），**比不改还糟**。

**实测（真实 Chrome + 磁盘上的 admin.css，验证页 `docs/album-table-cols.html`）**：

| 容器 | Directory | 行数 | 其他列 | 溢出 |
|---|---|---|---|---|
| 1240px | 300px（=280 内容 + padding） | **1 行** | 自适应 | 无 |
| 1040px | 300px | **1 行** | 自适应 | 表格 1095 > 容器 1038 → 横向滚动兜底 |
| 820px | 300px | **1 行** | 自适应 | 横向滚动兜底 |

`php -l` / `node --check` / `phpcs` 均 0 报错。

### 10.2 第二轮：Model(s) 竖排 + Title 过窄（2026-10-03）

老雷截图反馈：**Model(s) 的 badge 被压成竖排**（"陆萱萱" 一字一行、"×" 掉到下面），Title 输入框窄得只露开头。

**根因**：`core.css` 的 `.w2p-badge` 基础类**没有 `white-space: nowrap`**。中文可在任意两字之间断行，
于是 `.w2p-album-tag`（`display:inline-flex`，内含 label + × 按钮）的 **min-content 塌到 1 个字符 ≈ 30px**，
表格挤压时该列一路缩到那个下限 → 文字逐字竖排。

**改法**（全部收敛在 `#w2p-album-importer` 下，**不动 `core.css`** —— 那是全插件共享类）：

- 新增 `#w2p-album-importer .w2p-badge { white-space: nowrap; }` —— 把 min-content 抬到整串文本宽，
  该列自然有了下限（状态徽章一并受益）。
- 新增 `.w2p-album-input-title { min-width: 180px; }` —— 标题很长，90px 基线只够露开头。
- `col-status: 150px → 120px` —— 一个 "NEW"/"✓ 82" 的徽章用不着 150px。

**实测**：

| 项 | 改前 | 改后 |
|---|---|---|
| Model(s) 标签 | **竖排**（1 字/行） | **1 行**，宽 89px（含 ×） |
| Model(s) 列宽 | 73px | **112px** |
| Title 列宽 | 110px | **200px** |
| Status 列宽 | 138px（严重浪费） | 66px |
| 1240px 容器 | 无溢出 | **无溢出**（表 1238 = 容器 1238） |
| 1040px 容器 | 不滚动，但靠把 Model(s) 压成竖排换来 | 表 1224 > 1038 → 横向滚动兜底 |

取舍：1040px 以下改为横向滚动，换来各列可读。原先的"不滚动"是靠压垮 Model(s) 换的，不算数。

> 已知行为：状态文案较长时（"No images" / "Needs review"），该列 min-content 变大 → 表格可能略微变宽触发横向滚动。
> 这是 `nowrap` 的必然结果，比文字竖排可接受。

> 验证页 `docs/album-table-cols.html` 本轮**补正了保真度**：原先模范单元格只建纯文本 span，
> 漏了 `.w2p-album-tag-x`（× 占 3px gap + 14px），会把该列 min-content 量小。现已照 `#w2p-album-tag-tpl` 建全。

---

## 十一、目录点不开：`sanitize_text_field()` 把路径改坏了（2026-10-03）

**症状**：点 `[XIUREN秀人网] 2021 2022  陆萱萱 7800P 100套` 弹「Directory is not readable.」，
而相邻的兄弟目录都正常。

**根因**：AJAX handler 用 `sanitize_text_field()` 净化路径 —— 而它内部会做
`preg_replace( '/[\r\n\t ]+/', ' ', $filtered )`，**把连续空白折叠成一个空格**。
这个目录名里 "2021" 与 "2022" 之间恰好是**两个**空格，折叠后路径就对不上磁盘了：

```
磁盘真实路径 : .../2021 2022  陆萱萱...   is_dir = true
handler 处理后: .../2021 2022 陆萱萱...   is_dir = false  →  "Directory is not readable."
```

它是 `Processing/` 下**唯一**含连续空格的目录，所以只有它炸 —— 这也是为什么此前那 14 套导入一直没暴露。

**改法**：路径一律改走 `W2P_Album_Importer::sanitize_abs_path()`（本来就存在，做的是
「去 NUL/控制字符 + trim + 必须绝对路径 + 禁 `..`」，**不改写路径内容**）。共 4 个调用点：

| 位置 | 参数 | 不修的后果 |
|---|---|---|
| `ajax_browse` | `path` | 点不开目录（本次症状） |
| `ajax_scan` | `path` | 扫描失败 |
| `ajax_import_batch` | `src_dir` | **导入失败**（漏网之鱼，一并修） |
| `read_payload` | `abs_path` | 建图集失败 |

顺带给 `sanitize_abs_path()` 补了 NUL/控制字符剥离（NUL 会让 PHP 8 的 `is_dir()` 抛 `ValueError`）。

> `class-album-importer.php` 里另有一处 `sanitize_text_field( wp_basename( $src_dir ) )` —— 那是取**标题**用，
> 不参与路径查找，折叠空白无妨，刻意保留。

**验证**（走真实 handler 的净化链）：

| 输入 | 改前 | 改后 |
|---|---|---|
| 双空格目录 | `ERR: Directory is not readable.` | **OK（browse 100 子目录 / scan 100 候选）** |
| 单空格目录（对照） | OK | OK |
| `src_dir` 链路 | `is_dir` 为假 | `is_dir` **true** |
| `/etc`（根外） | — | 仍被 `is_path_allowed()` 拒绝 |
| 含 `..` / 相对路径 | — | `sanitize_abs_path()` 返回 `''` 拒绝 |
| 含 NUL | 可能 fatal | 剥离，不 fatal |

`php -l` 与 `phpcs` 均 0 报错。

> **可复用教训**：`sanitize_text_field()` **不能用于文件系统路径**。它是给「人类可读文本」设计的，会规范化空白；
> 而路径里的空白是有意义的。凡是把路径/文件名塞进 `sanitize_text_field()` 的地方，都是同一个坑。
