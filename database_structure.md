# 用戶討論區數據庫結構

## 數據庫信息
- **數據庫名稱**: `if0_40753568_discussions`
- **字符集**: utf8mb4
- **排序規則**: utf8mb4_unicode_ci

---

## 數據表結構

### 1. discuss 表（討論主題表）

**表名**: `discuss`

| 字段名 | 類型 | 說明 | 備註 |
|--------|------|------|------|
| id | int(11) | 討論主題ID | 主鍵，自動遞增 |
| topic | varchar(255) | 討論主題標題 | 必填 |
| category | varchar(50) | 分類 | 必填，值：ctf, security, general, food, others |
| content | text | 討論內容 | 必填 |
| users | varchar(100) | 作者名稱 | 必填 |
| date | date | 發布日期 | 必填，格式：YYYY-MM-DD |
| time | time | 發布時間 | 必填，格式：HH:MM:SS |
| views | int(11) | 瀏覽次數 | 默認值：0 |

**索引：**
- PRIMARY KEY (id)
- KEY idx_date (date)
- KEY idx_category (category)

---

### 2. user_discuss 表（用戶回覆表）

**表名**: `user_discuss`

| 字段名 | 類型 | 說明 | 備註 |
|--------|------|------|------|
| id | int(11) | 回覆ID | 主鍵，自動遞增 |
| discussion_id | int(11) | 關聯的討論主題ID | 必填，對應 discuss.id |
| author | varchar(100) | 回覆作者名稱 | 必填 |
| content | text | 回覆內容 | 必填 |
| date | date | 回覆日期 | 必填，格式：YYYY-MM-DD |
| time | time | 回覆時間 | 必填，格式：HH:MM:SS |

**索引：**
- PRIMARY KEY (id)
- KEY idx_discussion_id (discussion_id)
- KEY idx_date (date)

**關聯關係：**
- user_discuss.discussion_id → discuss.id（一對多關係）

---

## SQL 創建語句

### 創建 user_discuss 表

請執行 `create_user_discuss_table.sql` 文件中的 SQL 語句，或使用以下 SQL：

```sql
USE if0_40753568_discussions;

CREATE TABLE IF NOT EXISTS `user_discuss` (
    `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '回覆ID（主鍵）',
    `discussion_id` int(11) NOT NULL COMMENT '關聯的討論主題ID',
    `author` varchar(100) NOT NULL COMMENT '回覆作者名稱',
    `content` text NOT NULL COMMENT '回覆內容',
    `date` date NOT NULL COMMENT '回覆日期',
    `time` time NOT NULL COMMENT '回覆時間',
    PRIMARY KEY (`id`),
    KEY `idx_discussion_id` (`discussion_id`),
    KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用戶回覆表';
```

---

## 使用說明

### 查看表結構
```sql
DESCRIBE discuss;
DESCRIBE user_discuss;
```

### 查看所有表
```sql
SHOW TABLES;
```

### 查看某個討論的所有回覆
```sql
SELECT * FROM user_discuss WHERE discussion_id = 1;
```

### 查看討論及其回覆數量
```sql
SELECT d.*, COUNT(ud.id) as reply_count 
FROM discuss d 
LEFT JOIN user_discuss ud ON d.id = ud.discussion_id 
GROUP BY d.id;
```

---

## 注意事項

1. **字符集**: 確保使用 `utf8mb4` 以支持中文和特殊字符
2. **關聯關係**: `user_discuss.discussion_id` 應該對應 `discuss` 表中存在的 `id`
3. **數據完整性**: 刪除討論主題時，建議手動刪除相關回覆，或使用外鍵約束自動處理

---

## 已更新的代碼文件

以下文件已更新為使用 `user_discuss` 表：
- `discussions_api.php` - 所有回覆相關的 SQL 查詢
- `discuss.php` - 數據庫測試模式中的回覆查詢

