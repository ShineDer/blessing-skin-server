# 站点定制资产

本目录存放不随上游代码走、但属于本站点长期维护的定制内容。

## glass-theme.css

玻璃拟态主题（大圆角 + 毛玻璃 + 动效），替代原版 AdminLTE 视觉。

### 应用方式

主题通过后台的「自定义 CSS」选项生效，**不需要重建镜像或重启容器**：

1. 后台 → 定制（Customize）→ 自定义 CSS
2. 粘贴 `glass-theme.css` 全部内容 → 保存

或者直接写入数据库：

```bash
B64=$(base64 -w0 custom/glass-theme.css)
docker exec blessing-db mysql -u blessingskin -p"$DB_PASSWORD" blessingskin \
  -e "UPDATE options SET option_value=FROM_BASE64('$B64') WHERE option_name='custom_css';"
docker exec blessing-skin php artisan cache:clear
```

### 回滚

清空后台「自定义 CSS」文本框并保存，或：

```sql
UPDATE options SET option_value = '' WHERE option_name = 'custom_css';
```

随后执行 `php artisan cache:clear`。

### 维护约束

`custom_css` 经 Twig 的 `striptags` 过滤后注入 `<style>` 标签，因此：

- **CSS 中不能出现小于号字符**（会被当作 HTML 标签起始而截断）
- 大于号子选择器与 `@media` 已实测安全

### 关键适配点

| 位置 | 说明 |
|---|---|
| 渐变光斑背景 | `backdrop-filter` 需要背景有内容可折射，纯色底等于无效果 |
| 首页 navbar | 原为 `navbar-dark`（白字），改浅色玻璃后必须翻转文字色 |
| `.card .card-body` | Emotion 硬编码的 `#eff1f0`（`Item.tsx` / `Closet/styles.ts`）需置透明 |
| 错误页 | `errors/base.twig` 不引入 `shared.head`，主题覆盖不到 |

### 已知边界

错误页（403/404/500/503）与安装向导使用独立的 Spectre 体系，不加载本主题。
