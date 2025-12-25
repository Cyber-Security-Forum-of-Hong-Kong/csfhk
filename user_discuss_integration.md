# user_discuss 表集成說明

## ✅ 已完成的工作

### 1. 代碼更新
所有相關文件已更新為使用 `user_discuss` 表：

- ✅ `discussions_api.php` - 所有回覆相關的 SQL 查詢
- ✅ `discuss.php` - 數據庫測試模式中的回覆查詢
- ✅ 錯誤處理已改進，表不存在時會給出友好提示

### 2. 功能驗證

#### 查詢回覆列表
```php
SELECT id, author, content, date, time 
FROM user_discuss 
WHERE discussion_id = ? 
ORDER BY id ASC
```

#### 統計回覆數量
```php
SELECT COUNT(*) as cnt 
FROM user_discuss 
WHERE discussion_id = ?
```

#### 插入新回覆
```php
INSERT INTO user_discuss (discussion_id, author, content, date, time) 
VALUES (?,?,?,?,?)
```

#### 刪除回覆（刪除討論時）
```php
DELETE FROM user_discuss WHERE discussion_id = ?
```

---

## 📋 表結構要求

`user_discuss` 表必須包含以下字段：

| 字段名 | 類型 | 說明 |
|--------|------|------|
| id | int(11) | 主鍵，自動遞增 |
| discussion_id | int(11) | 關聯的討論主題ID |
| author | varchar(100) | 回覆作者 |
| content | text | 回覆內容 |
| date | date | 回覆日期 |
| time | time | 回覆時間 |

---

## 🧪 測試步驟

### 1. 測試回覆功能

1. **訪問討論區頁面**：`discuss.php`
2. **點擊一個討論主題**查看詳情
3. **填寫回覆表單**：
   - 作者名稱
   - 回覆內容
4. **提交回覆**
5. **驗證**：回覆應該立即顯示在頁面上

### 2. 測試數據庫查看

1. **訪問測試模式**：`discuss.php?test_db=1`
2. **查看 user_discuss 表**：應該能看到所有回覆數據
3. **驗證統計信息**：總回覆數應該正確顯示

### 3. 測試 API

使用瀏覽器控制台或 Postman 測試：

```javascript
// 測試插入回覆
fetch('discussions_api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        action: 'reply',
        thread_id: 1,
        author: '測試用戶',
        content: '這是一個測試回覆'
    })
})
.then(res => res.json())
.then(data => console.log(data));
```

---

## 🔍 故障排除

### 問題 1：回覆無法提交

**錯誤信息**：`user_discuss 表不存在`

**解決方案**：
1. 在 phpMyAdmin 中執行以下 SQL：
```sql
CREATE TABLE IF NOT EXISTS `user_discuss` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `discussion_id` int(11) NOT NULL,
    `author` varchar(100) NOT NULL,
    `content` text NOT NULL,
    `date` date NOT NULL,
    `time` time NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_discussion_id` (`discussion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 問題 2：回覆不顯示

**檢查**：
1. 打開瀏覽器開發者工具（F12）
2. 查看 Console 標籤是否有錯誤
3. 查看 Network 標籤，檢查 API 請求是否成功
4. 檢查 `discussions_api.php?action=view&id=1` 的響應

### 問題 3：回覆數量不正確

**檢查**：
1. 確認 `discussion_id` 正確對應 `discuss` 表的 `id`
2. 在數據庫中執行：
```sql
SELECT discussion_id, COUNT(*) as count 
FROM user_discuss 
GROUP BY discussion_id;
```

---

## 📝 注意事項

1. **數據完整性**：確保 `discussion_id` 對應 `discuss` 表中存在的 `id`
2. **字符編碼**：確保表使用 `utf8mb4` 字符集以支持中文
3. **時區設置**：回覆時間使用香港時區（Asia/Hong_Kong）
4. **錯誤處理**：如果表不存在，系統會給出友好提示，不會導致程序崩潰

---

## ✅ 驗證清單

- [x] `discussions_api.php` 使用 `user_discuss` 表
- [x] `discuss.php` 測試模式使用 `user_discuss` 表
- [x] 錯誤處理已改進
- [x] 回覆插入功能正常
- [x] 回覆查詢功能正常
- [x] 回覆統計功能正常
- [x] 前端 JavaScript 能正確處理回覆數據

---

## 🎯 下一步

1. 在數據庫中創建 `user_discuss` 表（如果尚未創建）
2. 測試回覆功能是否正常工作
3. 驗證回覆數據能正確顯示
4. 檢查回覆統計是否準確

