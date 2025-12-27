let discussions = [];
let currentView = 'list';
let selectedThreadId = null;
let filterCategory = 'all';
let searchQuery = '';
let currentCTFCategory = null;
let ctfChallenges = [];
let completedChallenges = [];

async function initializeStorage() {
    discussions = [];
    try {
        const apiEndpoint = window.API_ENDPOINT || 'discussions_api.php';
        const res = await fetch(apiEndpoint + '?action=list');
        
        // Check if response is ok
        if (!res.ok) {
            let errorMsg = 'Server error';
            try {
                const errorData = await res.json();
                errorMsg = errorData.error || errorData.message || `HTTP ${res.status}`;
                console.error('Server error response:', errorData);
            } catch (e) {
                errorMsg = `HTTP ${res.status}: ${res.statusText}`;
                console.error('Failed to parse error response:', e);
            }
            console.error('Failed to load discussions:', errorMsg);
            showNotification('載入討論失敗：' + errorMsg, 'error');
            discussions = []; // Ensure empty array
            return;
        }
        
        const data = await res.json();
        console.log('Discussions API response:', data);
        console.log('Response status:', res.status);
        console.log('Data.ok:', data.ok);
        console.log('Data.discussions:', data.discussions);
        
        if (data.ok && Array.isArray(data.discussions)) {
            discussions = data.discussions.map(d => ({
                id: d.id || 0,
                title: d.title || '',
                category: d.category || '',
                content: d.content || '',
                author: d.author || '',
                date: d.date || '',
                time: d.time || '',
                views: d.views || 0,
                reply_count: d.reply_count || 0,
                replies: []  // 詳細回覆在 viewThread 時再取
            }));
            console.log('Loaded discussions:', discussions.length);
            console.log('Discussions data:', discussions);
        } else {
            console.error('Failed to load discussions:', data.error || 'Unknown error');
            console.error('Full response:', data);
            if (data.error) {
                showNotification('載入討論失敗：' + data.error, 'error');
            }
            discussions = []; // Ensure empty array
        }
    } catch (e) {
        console.error('Error loading discussions:', e);
        showNotification('無法載入討論列表：' + (e.message || '請重新整理頁面'), 'error');
        discussions = [];
    }
    
    const completed = localStorage.getItem('csfhk_completed');
    if (completed) {
        completedChallenges = JSON.parse(completed);
    } else {
        completedChallenges = [];
        saveCompletedChallenges();
    }
}

function saveToStorage() {
    // 已改為 MySQL 儲存，這裡保留空函數兼容舊程式
}

function saveCompletedChallenges() {
    localStorage.setItem('csfhk_completed', JSON.stringify(completedChallenges));
}

function initializeCTFChallenges() {
    // All CTF challenges have been removed; keep structure for future use.
    ctfChallenges = [];
}

// 初始化討論區與 CTF 題目
document.addEventListener('DOMContentLoaded', async function() {
    // Only initialize if we're on a page with the discussions container
    const hasDiscussionsContainer = document.getElementById('threadsContainer');
    
    if (hasDiscussionsContainer) {
        await initializeStorage();     // 先從後端載入討論資料
        setupEventListeners();
        renderDiscussions();          // 載入完資料再渲染列表
    }
    
    // These can run on any page
    initializeCTFChallenges();
    setupCTFSpecialFeatures();
    updateStats();
});

function setupCTFSpecialFeatures() {
    window.getFlag = function() {
        console.log('CSFHK{console_master}');
        showNotification('請在控制台查看flag輸出', 'info');
    };
}

function renderDiscussions() {
    const container = document.getElementById('threadsContainer');
    
    if (!container) {
        console.warn('threadsContainer element not found - skipping render');
        return;
    }
    
    if (currentView === 'thread') {
        renderThreadDetail();
        return;
    }
    
    if (currentView === 'ctf') {
        renderCTFChallenges();
        return;
    }
    
    // Double check container still exists before setting innerHTML
    if (!container) {
        console.warn('Container disappeared before rendering');
        return;
    }
    
    container.innerHTML = '';
    
    // Ensure discussions is an array
    if (!Array.isArray(discussions)) {
        console.error('discussions is not an array:', discussions);
        discussions = [];
    }
    
    let filtered = discussions;
    
    if (filterCategory !== 'all') {
        filtered = filtered.filter(t => t.category === filterCategory);
    }
    
    if (searchQuery.trim()) {
        const query = searchQuery.toLowerCase();
        filtered = filtered.filter(t => 
            t.title && t.title.toLowerCase().includes(query) || 
            t.content && t.content.toLowerCase().includes(query) ||
            t.author && t.author.toLowerCase().includes(query)
        );
    }
    
    filtered.sort((a, b) => {
        const dateA = new Date(a.date + ' ' + (a.time || '00:00'));
        const dateB = new Date(b.date + ' ' + (b.time || '00:00'));
        return dateB - dateA;
    });
    
    if (filtered.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">暫無討論主題，成為第一個發表的人吧！</p>';
        return;
    }

    console.log('Rendering', filtered.length, 'discussions');
    console.log('Filtered discussions:', filtered);
    
    if (filtered.length === 0 && discussions.length > 0) {
        console.warn('All discussions filtered out! Total:', discussions.length, 'Filter category:', filterCategory, 'Search:', searchQuery);
    }
    
    filtered.forEach(thread => {
        try {
            const threadElement = createThreadElement(thread);
            if (threadElement) {
                container.appendChild(threadElement);
            } else {
                console.error('Failed to create element for thread:', thread);
            }
        } catch (e) {
            console.error('Error creating thread element:', e, thread);
        }
    });
    
    console.log('Rendered', container.children.length, 'thread elements');
}

function createThreadElement(thread) {
    const threadDiv = document.createElement('div');
    threadDiv.className = 'thread-item';
    // Use reply_count from database if available, otherwise use replies array length
    const repliesCount = thread.reply_count !== undefined ? thread.reply_count : (thread.replies ? thread.replies.length : 0);
    threadDiv.innerHTML = `
        <div class="thread-header">
            <div class="thread-title-section">
                <div class="thread-title" onclick="viewThread(${thread.id})">${escapeHtml(thread.title)}</div>
            </div>
            <div class="thread-actions">
                <span class="thread-category">${getCategoryName(thread.category)}</span>
                <button class="btn-delete-thread" onclick="deleteThread(${thread.id}, event)" title="刪除">×</button>
            </div>
        </div>
        <div class="thread-content" onclick="viewThread(${thread.id})">${escapeHtml(thread.content.substring(0, 200))}${thread.content.length > 200 ? '...' : ''}</div>
        <div class="thread-meta">
            <span>👤 ${escapeHtml(thread.author)}</span>
            <span>📅 ${thread.date} ${thread.time || ''}</span>
            <span>💬 ${repliesCount} 回覆</span>
            <span>👁️ ${thread.views || 0} 瀏覽</span>
        </div>
    `;
    return threadDiv;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function viewThread(id) {
    // 向後端請求該主題詳情與回覆，順便更新瀏覽次數
    try {
        const apiEndpoint = window.API_ENDPOINT || 'discussions_api.php';
        const res = await fetch(`${apiEndpoint}?action=view&id=${encodeURIComponent(id)}`);
        const data = await res.json();
        if (data.ok && data.thread) {
            const idx = discussions.findIndex(t => t.id == id);
            const fullThread = {
                ...data.thread,
                replies: data.replies || []
            };
            if (idx >= 0) {
                discussions[idx] = fullThread;
            } else {
                discussions.push(fullThread);
            }
            selectedThreadId = id;
            currentView = 'thread';
            renderDiscussions();
        } else {
            showNotification('載入主題失敗：' + (data.error || ''), 'error');
        }
    } catch (e) {
        showNotification('伺服器錯誤，請稍後再試', 'error');
    }
}

function renderThreadDetail() {
    const container = document.getElementById('threadsContainer');
    const thread = discussions.find(t => t.id === selectedThreadId);
    
    if (!thread) {
        currentView = 'list';
        renderDiscussions();
        return;
    }
    
    thread.views = (thread.views || 0) + 1;
    saveToStorage();
    
    const replies = thread.replies || [];
    
    // 确保表单默认隐藏
    container.innerHTML = `
        <div class="thread-detail-actions">
            <button class="btn-back" onclick="backToList()">← 返回列表</button>
        </div>
        <div class="thread-detail">
            <div class="thread-detail-header">
                <h2 class="thread-detail-title">${escapeHtml(thread.title)}</h2>
                <span class="thread-category">${getCategoryName(thread.category)}</span>
            </div>
            <div class="thread-detail-meta">
                <span>👤 ${escapeHtml(thread.author)}</span>
                <span>📅 ${thread.date} ${thread.time || ''}</span>
                <span>👁️ ${thread.views} 瀏覽</span>
            </div>
            <div class="thread-detail-content">${escapeHtml(thread.content).replace(/\n/g, '<br>')}</div>
        </div>
        <div class="replies-section">
            <div class="replies-header">
                <h3 class="replies-title">回覆 (${replies.length})</h3>
                <div class="reply-button-wrapper">
                    <button class="btn-reply-toggle" id="toggleReplyForm" onclick="toggleReplyForm()">💬 發表回覆</button>
                </div>
            </div>
            <div class="replies-container-wrapper">
                <div class="replies-container" id="repliesContainer">
                    ${replies.length > 0 ? replies.map(reply => `
                        <div class="reply-item">
                            <div class="reply-header">
                                <strong>${escapeHtml(reply.author)}</strong>
                                <span class="reply-date">${reply.date} ${reply.time || ''}</span>
                            </div>
                            <div class="reply-content">${escapeHtml(reply.content).replace(/\n/g, '<br>')}</div>
                        </div>
                    `).join('') : '<div class="no-replies-message">暫無回覆，成為第一個回覆的人吧！</div>'}
                </div>
            </div>
            <div class="reply-form-container" id="replyFormContainer">
                <div class="reply-form-header">
                    <h4>發表回覆</h4>
                    <button type="button" class="btn-cancel-reply" onclick="cancelReply()" title="取消發表">✕</button>
                </div>
                <form id="replyForm" onsubmit="submitReply(event)">
                    <div class="form-group" style="display: none;">
                        <label for="replyAuthor">您的名稱</label>
                        <input type="text" id="replyAuthor" value="">
                    </div>
                    <div class="form-group">
                        <label for="replyContent">回覆內容</label>
                        <textarea id="replyContent" rows="4" required></textarea>
                    </div>
                    <div class="reply-form-actions">
                        <button type="button" class="btn-cancel" onclick="cancelReply()">取消</button>
                        <button type="submit" class="btn-submit">發表回覆</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    // 确保表单默认隐藏，按钮默认显示
    setTimeout(() => {
        const formContainer = document.getElementById('replyFormContainer');
        const toggleBtn = document.getElementById('toggleReplyForm');
        
        if (formContainer) {
            formContainer.classList.remove('show');
            console.log('表单已设置为隐藏');
        }
        
        if (toggleBtn) {
            toggleBtn.innerHTML = '💬 發表回覆';
            toggleBtn.style.background = 'linear-gradient(135deg, var(--accent-green) 0%, var(--accent-cyan) 100%)';
            toggleBtn.style.borderColor = 'var(--accent-green)';
            toggleBtn.style.boxShadow = '0 4px 12px rgba(0, 255, 136, 0.3)';
            console.log('按钮已设置为默认状态');
        }
    }, 100);
}

function backToList() {
    currentView = 'list';
    selectedThreadId = null;
    currentCTFCategory = null;
    renderDiscussions();
}

function renderCTFChallenges() {
    const container = document.getElementById('threadsContainer');
    if (!container) return;
    
    const challenges = ctfChallenges.filter(c => c.category === currentCTFCategory);
    const categoryName = getCategoryName(currentCTFCategory);
    
    container.innerHTML = `
        <div class="ctf-header-actions">
            <button class="btn-back" onclick="backToList()">← 返回分類</button>
        </div>
        <div class="ctf-challenges-container">
            <h3 class="ctf-category-title">${categoryName} 挑戰</h3>
            ${challenges.length === 0 ? '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">此分類暫無挑戰題目</p>' : ''}
            ${challenges.map(challenge => createCTFChallengeElement(challenge)).join('')}
        </div>
    `;
}

function createCTFChallengeElement(challenge) {
    const isCompleted = completedChallenges.includes(challenge.id);
    const difficultyClass = challenge.difficulty === 'easy' ? 'easy' : challenge.difficulty === 'medium' ? 'medium' : 'hard';
    
    return `
        <div class="ctf-challenge-card ${isCompleted ? 'completed' : ''}" data-challenge-id="${challenge.id}">
            <div class="ctf-challenge-header">
                <div class="ctf-challenge-title-section">
                    <h4 class="ctf-challenge-title">${escapeHtml(challenge.title)}</h4>
                    <span class="difficulty ${difficultyClass}">${challenge.difficulty === 'easy' ? '入門' : challenge.difficulty === 'medium' ? '中級' : '高級'}</span>
                    <span class="ctf-points">${challenge.points} 分</span>
                    ${isCompleted ? '<span class="ctf-completed-badge">✓ 已完成</span>' : ''}
                </div>
            </div>
            <div class="ctf-challenge-description">
                ${escapeHtml(challenge.description).replace(/\n/g, '<br>')}
            </div>
            ${!isCompleted ? `
                <div class="ctf-challenge-actions">
                    <button class="btn-show-hint" onclick="showHint(${challenge.id})">顯示提示</button>
                    ${challenge.id === 7 ? `
                        <div class="ctf-cookie-instruction">
                            <p><strong>說明：</strong>請在瀏覽器控制台執行以下命令來設置Cookie：</p>
                            <code class="ctf-code">document.cookie = "secret_flag=CSFHK-cookie_master"</code>
                            <p>設置後，請重新加載頁面，然後點擊提交按鈕。</p>
                        </div>
                        <button class="btn-submit-flag" onclick="submitFlag(${challenge.id})" style="margin-top: 1rem;">檢查Cookie並提交</button>
                    ` : `
                        <div class="ctf-flag-input-group">
                            <input type="text" id="flag-input-${challenge.id}" class="ctf-flag-input" placeholder="輸入 Flag (格式: CSFHK{...} 或 CSFHK-{...})">
                            <button class="btn-submit-flag" onclick="submitFlag(${challenge.id})">提交</button>
                        </div>
                    `}
                    <div id="hint-${challenge.id}" class="ctf-hint" style="display: none;">
                        <strong>提示：</strong>${escapeHtml(challenge.hint)}
                    </div>
                </div>
            ` : `
                <div class="ctf-completed-message">
                    <p>🎉 恭喜完成此挑戰！</p>
                </div>
            `}
        </div>
    `;
}

function showHint(challengeId) {
    const hintElement = document.getElementById(`hint-${challengeId}`);
    if (hintElement) {
        hintElement.style.display = hintElement.style.display === 'none' ? 'block' : 'none';
    }
}

async function submitFlag(challengeId) {
    const challenge = ctfChallenges.find(c => c.id === challengeId);
    if (!challenge) return;
    
    if (challengeId === 7) {
        if (document.cookie.includes('secret_flag=CSFHK-cookie_master')) {
            if (!completedChallenges.includes(challengeId)) {
                completedChallenges.push(challengeId);
                saveCompletedChallenges();
                showNotification(`🎉 正確！獲得 ${challenge.points} 分！`, 'success');
                renderCTFChallenges();
                updateStats();
            } else {
                showNotification('此挑戰已完成', 'info');
            }
        } else {
            showNotification('請先設置Cookie，然後重新加載頁面', 'error');
        }
        return;
    }
    
    const inputElement = document.getElementById(`flag-input-${challengeId}`);
    const userFlag = inputElement ? inputElement.value.trim() : '';
    
    if (!userFlag) {
        showNotification('請輸入Flag', 'error');
        return;
    }

    try {
        const response = await fetch('ctf_check.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: challengeId,
                flag: userFlag
            })
        });

        const result = await response.json();

        if (result.ok) {
            if (!completedChallenges.includes(challengeId)) {
                completedChallenges.push(challengeId);
                saveCompletedChallenges();
                showNotification(`🎉 正確！獲得 ${challenge.points} 分！`, 'success');
                renderCTFChallenges();
                updateStats();
                if (inputElement) {
                    inputElement.value = '';
                }
            } else {
                showNotification('此挑戰已完成', 'info');
            }
        } else {
            showNotification('Flag 不正確，請再試試', 'error');
            if (inputElement) {
                inputElement.style.borderColor = 'var(--danger)';
                setTimeout(() => {
                    inputElement.style.borderColor = '';
                }, 2000);
            }
        }
    } catch (e) {
        showNotification('伺服器錯誤，請稍後再試', 'error');
    }
}

// 將函數綁定到 window 對象，確保可以在 HTML 中調用
window.toggleReplyForm = function(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const formContainer = document.getElementById('replyFormContainer');
    const toggleBtn = document.getElementById('toggleReplyForm');
    
    if (!formContainer) {
        console.error('replyFormContainer 未找到');
        return;
    }
    
    if (!toggleBtn) {
        console.error('toggleReplyForm 按钮未找到');
        return;
    }
    
    const isHidden = !formContainer.classList.contains('show');
    
    console.log('切换表单状态，当前状态:', isHidden ? '隐藏' : '显示');
    
    if (isHidden) {
        // 显示表单
        formContainer.classList.add('show');
        toggleBtn.innerHTML = '✕ 取消發表';
        toggleBtn.style.background = 'linear-gradient(135deg, #ff3366 0%, #ff6699 100%)';
        toggleBtn.style.borderColor = '#ff3366';
        toggleBtn.style.boxShadow = '0 4px 12px rgba(255, 51, 102, 0.4)';
        console.log('表单已显示');
    } else {
        // 隐藏表单
        formContainer.classList.remove('show');
        toggleBtn.innerHTML = '💬 發表回覆';
        toggleBtn.style.background = 'linear-gradient(135deg, #00ff88 0%, #00d4ff 100%)';
        toggleBtn.style.borderColor = '#00ff88';
        toggleBtn.style.boxShadow = '0 4px 12px rgba(0, 255, 136, 0.3)';
        // 清空表單
        const form = document.getElementById('replyForm');
        if (form) form.reset();
        console.log('表单已隐藏');
    }
}

window.cancelReply = function(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const formContainer = document.getElementById('replyFormContainer');
    const toggleBtn = document.getElementById('toggleReplyForm');
    const form = document.getElementById('replyForm');
    
    if (!formContainer) {
        console.error('replyFormContainer 未找到');
        return;
    }
    
    if (!toggleBtn) {
        console.error('toggleReplyForm 按钮未找到');
        return;
    }
    
    // 隐藏表单
    formContainer.classList.remove('show');
    toggleBtn.innerHTML = '💬 發表回覆';
    toggleBtn.style.background = 'linear-gradient(135deg, #00ff88 0%, #00d4ff 100%)';
    toggleBtn.style.borderColor = '#00ff88';
    toggleBtn.style.boxShadow = '0 4px 12px rgba(0, 255, 136, 0.3)';
    
    if (form) {
        form.reset();
    }
    
    console.log('表单已取消并隐藏');
}

window.submitReply = function(event) {
    event.preventDefault();
    const thread = discussions.find(t => t.id === selectedThreadId);
    if (!thread) return;
    
    const content = document.getElementById('replyContent');
    
    if (!content) {
        showNotification('表單元素未找到', 'error');
        return;
    }
    
    const contentValue = content.value.trim();
    
    if (!contentValue) {
        showNotification('請填寫回覆內容', 'error');
        return;
    }
    
    const apiEndpoint = window.API_ENDPOINT || 'discussions_api.php';
    const csrfToken = window.CSRF_TOKEN || '';
    fetch(apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'reply',
            thread_id: thread.id,
            content: contentValue,
            csrf_token: csrfToken
        })
    }).then(res => res.json()).then(data => {
        if (data.ok) {
            const form = document.getElementById('replyForm');
            if (form) form.reset();
            window.cancelReply(); // 提交後隱藏表單
            viewThread(thread.id);
            showNotification('回覆發布成功！', 'success');
        } else {
            showNotification('回覆失敗：' + (data.error || ''), 'error');
        }
    }).catch((error) => {
        console.error('回覆提交錯誤:', error);
        showNotification('伺服器錯誤，請稍後再試', 'error');
    });
}

function deleteThread(id, event) {
    event.stopPropagation();
    if (confirm('確定要刪除此主題嗎？')) {
        const apiEndpoint = window.API_ENDPOINT || 'discussions_api.php';
        const csrfToken = window.CSRF_TOKEN || '';
        fetch(apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'delete', id, csrf_token: csrfToken })
        }).then(async res => {
            const data = await res.json();
            if (!res.ok && !data.ok) {
                throw new Error(data.error || 'Server error');
            }
            return data;
        }).then(async data => {
            if (data.ok) {
                // Reload discussions from server to ensure the list is up to date
                try {
                    const res = await fetch(apiEndpoint + '?action=list');
                    const listData = await res.json();
                    if (listData.ok && Array.isArray(listData.discussions)) {
                        discussions = listData.discussions.map(d => ({
                            ...d,
                            replies: []
                        }));
                    } else {
                        // Fallback: remove from local array if reload fails
                        discussions = discussions.filter(t => t.id !== id);
                    }
                } catch (e) {
                    // Fallback: remove from local array if reload fails
                    console.error('Error reloading discussions:', e);
                    discussions = discussions.filter(t => t.id !== id);
                }
                renderDiscussions();
                showNotification('主題已刪除', 'success');
            } else {
                showNotification('刪除失敗：' + (data.error || 'Unknown error'), 'error');
            }
        }).catch(err => {
            showNotification('刪除失敗：' + (err.message || '伺服器錯誤，請稍後再試'), 'error');
            console.error('Error deleting thread:', err);
        });
    }
}

function getCategoryName(category) {
    const categoryMap = {
        'ctf': 'CTF 題目',
        'security': '網絡安全',
        'general': '一般討論',
        'news': '新聞分享'
    };
    return categoryMap[category] || category;
}

function setupEventListeners() {
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            // 只對錨點 (#section) 做平滑滾動，其餘交回瀏覽器正常跳轉
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' });
                    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                }
            }
        });
    });

    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            currentCTFCategory = category;
            currentView = 'ctf';
            document.getElementById('ctf').scrollIntoView({ behavior: 'smooth' });
            renderDiscussions();
        });
    });

    const newPostBtn = document.getElementById('newPostBtn');
    const newPostModal = document.getElementById('newPostModal');
    const closeModal = document.getElementById('closeModal');
    const newPostForm = document.getElementById('newPostForm');

    if (newPostBtn) {
        newPostBtn.addEventListener('click', () => {
            newPostModal.classList.add('active');
        });
    }

    if (closeModal) {
        closeModal.addEventListener('click', () => {
            newPostModal.classList.remove('active');
        });
    }

    if (newPostModal) {
        newPostModal.addEventListener('click', (e) => {
            if (e.target === newPostModal) {
                newPostModal.classList.remove('active');
            }
        });
    }

    if (newPostForm) {
        newPostForm.addEventListener('submit', (e) => {
            e.preventDefault();
            createNewPost();
        });
    }

    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            renderDiscussions();
        });
    }

    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', (e) => {
            filterCategory = e.target.value;
            renderDiscussions();
        });
    }
}

function createNewPost() {
    const title = document.getElementById('postTitle').value.trim();
    const category = document.getElementById('postCategory').value;
    const content = document.getElementById('postContent').value.trim();

    if (!title || !content) {
        showNotification('請填寫所有必填欄位', 'error');
        return;
    }

    const apiEndpoint = window.API_ENDPOINT || 'discussions_api.php';
    const csrfToken = window.CSRF_TOKEN || '';
    fetch(apiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'create',
            title,
            category,
            content,
            csrf_token: csrfToken
        })
    }).then(async res => {
        const data = await res.json();
        if (!res.ok && !data.ok) {
            throw new Error(data.error || 'Server error');
        }
        return data;
    }).then(async data => {
        if (data.ok && data.thread) {
            // Reload discussions from server to ensure everyone sees the new post
            try {
                const res = await fetch(apiEndpoint + '?action=list');
                const listData = await res.json();
                if (listData.ok && Array.isArray(listData.discussions)) {
                    discussions = listData.discussions.map(d => ({
                        ...d,
                        replies: []
                    }));
                } else {
                    // Fallback: add to local array if reload fails
                    discussions.unshift({ ...data.thread, replies: [] });
                }
            } catch (e) {
                // Fallback: add to local array if reload fails
                console.error('Error reloading discussions:', e);
                discussions.unshift({ ...data.thread, replies: [] });
            }
            
            renderDiscussions();
            
            document.getElementById('newPostModal').classList.remove('active');
            document.getElementById('newPostForm').reset();
            
            showNotification('主題發布成功！', 'success');
            document.getElementById('discussions').scrollIntoView({ behavior: 'smooth' });
        } else {
            showNotification('發布失敗：' + (data.error || 'Unknown error'), 'error');
        }
    }).catch(err => {
        showNotification('伺服器錯誤：' + (err.message || '請稍後再試'), 'error');
        console.error('Error creating post:', err);
    });
}

function animateTerminal() {
    const terminalOutput = document.querySelector('.terminal-output');
    if (!terminalOutput) return;
    const lines = terminalOutput.querySelectorAll('p');
    
    lines.forEach((line, index) => {
        line.style.opacity = '0';
        setTimeout(() => {
            line.style.transition = 'opacity 0.5s ease';
            line.style.opacity = '1';
        }, index * 200);
    });
}

function updateStats() {
    const userCountElement = document.getElementById('userCount');
    const topicCountElement = document.getElementById('topicCount');
    
    if (userCountElement) {
        const uniqueAuthors = new Set(discussions.map(d => d.author).concat(
            discussions.flatMap(d => (d.replies || []).map(r => r.author))
        )).size;
        userCountElement.textContent = uniqueAuthors || 0;
    }
    
    if (topicCountElement) {
        topicCountElement.textContent = discussions.length;
    }
    
    const challengeCounts = {
        'web': 0,
        'crypto': 0,
        'forensics': 0,
        'reverse': 0,
        'pwn': 0,
        'misc': 0
    };
    
    ctfChallenges.forEach(c => {
        if (challengeCounts.hasOwnProperty(c.category)) {
            challengeCounts[c.category]++;
        }
    });
    
    document.querySelectorAll('.category-card').forEach((card) => {
        const category = card.getAttribute('data-category');
        if (category && challengeCounts[category] !== undefined) {
            const statsSpan = card.querySelector('.category-discussion-count');
            if (statsSpan) {
                const count = challengeCounts[category];
                const completed = ctfChallenges.filter(c => c.category === category && completedChallenges.includes(c.id)).length;
                statsSpan.textContent = `${count} 題目 ${completed > 0 ? `(${completed} 已完成)` : ''}`;
            }
        }
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const bgColor = type === 'success' ? 'var(--accent-green)' : type === 'error' ? 'var(--danger)' : 'var(--accent-cyan)';
    notification.style.cssText = `
        position: fixed;
        top: 100px;
        right: 20px;
        background: ${bgColor};
        color: var(--bg-primary);
        padding: 1rem 2rem;
        border-radius: 4px;
        font-weight: bold;
        z-index: 3000;
        animation: slideIn 0.3s ease;
        box-shadow: 0 5px 20px rgba(0, 255, 136, 0.4);
        font-family: 'JetBrains Mono', monospace;
    `;
    notification.textContent = message;
    
    if (!document.getElementById('notification-style')) {
        const style = document.createElement('style');
        style.id = 'notification-style';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => {
            if (notification.parentNode) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

window.addEventListener('scroll', () => {
    const sections = document.querySelectorAll('.section');
    const navLinks = document.querySelectorAll('.nav-link');
    
    let current = '';
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        if (pageYOffset >= sectionTop - 200) {
            current = section.getAttribute('id');
        }
    });
    
    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${current}`) {
            link.classList.add('active');
        }
    });
});

const terminalCommand = document.querySelector('.command');
if (terminalCommand) {
    const commandText = terminalCommand.textContent;
    terminalCommand.textContent = '';
    let index = 0;
    
    function typeCommand() {
        if (index < commandText.length) {
            terminalCommand.textContent += commandText.charAt(index);
            index++;
            setTimeout(typeCommand, 100);
        }
    }
    
    setTimeout(typeCommand, 1000);
}

setInterval(updateStats, 10000);


