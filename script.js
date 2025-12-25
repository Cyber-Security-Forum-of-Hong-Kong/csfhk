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
        const res = await fetch('discussions_api.php?action=list');
        
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
    ctfChallenges = [
        {
            id: 1,
            category: 'web',
            title: 'Hidden Flag',
            description: 'Flag隱藏在這個頁面的源代碼中，你能找到它嗎？提示：查看HTML註釋。',
            difficulty: 'easy',
            points: 10,
            hint: '右鍵點擊頁面，選擇"查看頁面源代碼"或按F12查看開發者工具'
        },
        {
            id: 2,
            category: 'web',
            title: 'Base64 Encode',
            description: '解碼這段Base64編碼的文字：Q1NGSEst{eG9uZ19rb25nX2ZvcnVt}',
            difficulty: 'easy',
            points: 10,
            hint: '使用在線Base64解碼工具或JavaScript atob()函數'
        },
        {
            id: 3,
            category: 'crypto',
            title: 'Caesar Cipher',
            description: '解碼這段凱撒密碼（移位3）：FVIKN{oryh_qhw_zrun}',
            difficulty: 'easy',
            points: 15,
            hint: '每個字母向前移動3個位置（解密時向後移動3個位置）'
        },
        {
            id: 4,
            category: 'crypto',
            title: 'ROT13 Challenge',
            description: '解碼這段ROT13編碼：PFSUX{uryyb_plorefrphevgl}',
            difficulty: 'easy',
            points: 15,
            hint: 'ROT13是每個字母移動13個位置的凱撒密碼（加密和解密相同）'
        },
        {
            id: 5,
            category: 'misc',
            title: 'Flag in Console',
            description: '打開瀏覽器的開發者工具（F12），在控制台（Console）中輸入：getFlag() 然後按Enter',
            difficulty: 'easy',
            points: 10,
            hint: '按F12打開開發者工具，切換到Console標籤，然後輸入getFlag()'
        },
        {
            id: 6,
            category: 'misc',
            title: 'Hexadecimal Decode',
            description: '將這段十六進制轉換為文字：435346484B7B6865785F6465636F64657D',
            difficulty: 'easy',
            points: 15,
            hint: '每兩個十六進制字符代表一個ASCII字符'
        },
        {
            id: 7,
            category: 'web',
            title: 'Cookie Challenge',
            description: '設置一個名為"secret_flag"的Cookie，值為"CSFHK-cookie_master"，然後重新加載頁面。提示：使用JavaScript document.cookie設置。',
            difficulty: 'medium',
            points: 20,
            hint: '在控制台執行: document.cookie = "secret_flag=CSFHK-cookie_master"'
        },
        {
            id: 8,
            category: 'crypto',
            title: 'Binary to Text',
            description: '將這段二進制轉換為文字：01000011 01010011 01000110 01001000 01001011 01111011 01100010 01101001 01101110 01100001 01110010 01111001 01111101',
            difficulty: 'medium',
            points: 20,
            hint: '每8位二進制數代表一個ASCII字符'
        },
        {
            id: 9,
            category: 'forensics',
            title: 'Image Metadata',
            description: '查看這張圖片的EXIF數據，flag在相機製造商字段中：data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjx0ZXh0IHg9IjEwIiB5PSI1MCIgZm9udC1zaXplPSIyMCI+Q1NGSEst{eXhpZl9kYXRhfTwvdGV4dD48L3N2Zz4=',
            difficulty: 'medium',
            points: 25,
            hint: '某些圖片包含隱藏的元數據'
        },
        {
            id: 10,
            category: 'web',
            title: 'JavaScript Obfuscation',
            description: '這段JavaScript代碼被混淆了，你能執行它並找到flag嗎？在控制台執行：eval(atob("Y29uc29sZS5sb2coJ0NTRktILXtzY3JpcHRfb2JmdXNjYXRpb259Jyk7"))',
            difficulty: 'medium',
            points: 25,
            hint: '在瀏覽器控制台中複製並運行這段代碼'
        },
        {
            id: 11,
            category: 'reverse',
            title: 'Packed XOR',
            description: '以下十六進制數據代表一段被XOR加密的文字，使用密鑰0x37解密並提交flag：525343465b0d445140155b0c5b1b414c4415595d0d17411b1b5a',
            difficulty: 'hard',
            points: 40,
            hint: '將hex轉byte，每個byte與0x37異或後轉ASCII'
        },
        {
            id: 12,
            category: 'pwn',
            title: 'Format String Leak',
            description: '本題flag格式為CSFHK{format_string}. 典型printf("%s")漏洞可使用%s連續輸出棧內容，請推測flag並提交。',
            difficulty: 'hard',
            points: 45,
            hint: 'CTF常見格式化字符串利用，flag已知格式，可直接提交'
        },
        {
            id: 13,
            category: 'misc',
            title: 'Layered Base',
            description: '這串字串被連續三次Base64編碼，解開它：QzFHRUhLe0IxbmFyeV9iNHNlbTY0X2YwbjB0X2gxZDFuXzNOMV0=',
            difficulty: 'hard',
            points: 35,
            hint: '連續解三次Base64，注意大小寫與字元'
        },
        {
            id: 14,
            category: 'crypto',
            title: 'Vigenère Cipher',
            description: '解碼這段維吉尼亞密碼，密鑰是"HONGKONG"：\nJGSNU{jvmlbrxo_qvvose}',
            difficulty: 'hard',
            points: 40,
            hint: '維吉尼亞密碼使用多字母替換，需要對每個字母使用對應的密鑰字母進行凱撒密碼解密'
        },
        {
            id: 15,
            category: 'crypto',
            title: 'Rail Fence Cipher',
            description: '解碼這段欄柵密碼（Rail Fence，3行）：\n讀取順序：第1行、第2行、第3行\n密文：C_K_LH_OK{_s_er_ou_g}\n按3行欄柵密碼規則重新排列',
            difficulty: 'hard',
            points: 35,
            hint: '將字母按Z字形分3行排列：第1行(位置0,4,8...)，第2行(位置1,3,5...)，第3行(位置2,6,10...)'
        },
        {
            id: 16,
            category: 'crypto',
            title: 'Playfair Cipher',
            description: '解碼這段Playfair密碼，密鑰矩陣的關鍵詞是"CSFHK"：\n密文：QB QZ FB ZQ QF BF QZ QF\n提示：I和J視為同一字母',
            difficulty: 'hard',
            points: 45,
            hint: 'Playfair密碼使用5x5矩陣，將字母對進行替換'
        },
        {
            id: 17,
            category: 'crypto',
            title: 'Multi-Base Challenge',
            description: '這段文字經過Base64編碼：\nQ1NGSEt7bXVsdGliYXNlfQ==\n解碼後即可得到flag',
            difficulty: 'hard',
            points: 40,
            hint: '直接使用Base64解碼工具或JavaScript atob()函數'
        },
        {
            id: 18,
            category: 'crypto',
            title: 'RSA Mini Challenge',
            description: '這是一個簡化的RSA加密挑戰：\nn = 77, e = 7, c = 68\n求解明文m（答案轉換為字母，a=1, b=2...，然後轉換為flag格式）',
            difficulty: 'hard',
            points: 50,
            hint: 'n=77=7*11，phi(n)=60，計算私鑰d=43，然後用m = c^d mod n = 68^43 mod 77 = 19，對應字母s'
        },
        {
            id: 19,
            category: 'crypto',
            title: 'Substitution Cipher',
            description: '解碼這段簡單替換密碼：\nXZT YJH YJH QZR ZH YJH ZJHT ZQKFX\n提示：這是一個單字母替換密碼，分析字母頻率',
            difficulty: 'hard',
            points: 38,
            hint: '使用字母頻率分析，最常見的字母通常是E、T、A等'
        },
        {
            id: 20,
            category: 'crypto',
            title: 'XOR Cipher',
            description: '這段文字被XOR加密，密鑰是"HKG"（循環使用）：\n密文（hex）：0b180100003c30243517282e3823223a36\n提示：將hex轉換為bytes，每個byte與對應位置的密鑰byte（H=72, K=75, G=71）進行XOR',
            difficulty: 'hard',
            points: 42,
            hint: '將hex轉換為bytes，每個byte與對應位置的密鑰byte進行XOR運算'
        },
        {
            id: 21,
            category: 'crypto',
            title: 'Affine Cipher',
            description: '解碼這段仿射密碼：\n密文：SUHRG{Lcipv_za_xcspyfz}\n加密公式：(a*x + b) mod 26，其中a=5, b=8\n提示：需要計算a的模逆元（5^(-1) mod 26 = 21）',
            difficulty: 'hard',
            points: 43,
            hint: '解密公式：x = a^(-1) * (y - b) mod 26，其中a^(-1) = 21'
        },
        {
            id: 22,
            category: 'crypto',
            title: 'Multi-Layer Encoding',
            description: '這段文字經過三重編碼：\n1. Base64編碼\n2. 轉換為十六進制\n3. 字符串反轉\n密文（hex，已反轉）：9346d62607252326a65375a566a485a55364742666c67446376585267347543574e41315\n提示：需要按照相反順序解碼',
            difficulty: 'hard',
            points: 55,
            hint: '先將hex反轉，再hex轉ASCII，最後Base64解碼'
        },
        {
            id: 23,
            category: 'crypto',
            title: 'Double XOR Encryption',
            description: '這段文字被XOR加密了兩次：\n密文（hex）：4353464b4b7b6d766c74695c786f725c6368616f6c656e64657d\n第一次XOR密鑰：KEY1\n第二次XOR密鑰：KEY2\n提示：需要按照相反順序進行兩次XOR解密',
            difficulty: 'hard',
            points: 52,
            hint: '先用KEY2進行XOR，再用KEY1進行XOR（因為XOR是可逆的，順序不影響結果）'
        },
        {
            id: 24,
            category: 'crypto',
            title: 'Columnar Transposition',
            description: '解碼這段欄位換位密碼：\n密文：C{mtpiFoaasnHlrni}Ku_stScnroo\n換位密鑰：CSFHK\n提示：需要根據密鑰字母順序重新排列列',
            difficulty: 'hard',
            points: 48,
            hint: '將密文按列排列，然後根據密鑰字母順序重新排列列'
        },
        {
            id: 25,
            category: 'crypto',
            title: 'RSA Advanced',
            description: '這是一個更複雜的RSA挑戰：\nn = 323, e = 7, c = 18\n求解明文m（答案轉換為字母，a=1, b=2...，然後轉換為flag格式）\n提示：需要分解n並計算私鑰',
            difficulty: 'hard',
            points: 60,
            hint: 'n=323=17*19，phi(n)=288，計算d=247，m = c^d mod n = 18^247 mod 323 = 18，對應字母r'
        },
        {
            id: 26,
            category: 'crypto',
            title: 'Combined Cipher',
            description: '這段文字使用了組合加密：\n1. 先進行Caesar密碼（移位5）\n2. 再進行XOR加密（密鑰：XOR）\n密文（hex）：101719151f29303b203f212132260d30212735252525\n提示：需要按照相反順序解密',
            difficulty: 'hard',
            points: 58,
            hint: '先用XOR密鑰XOR解密，再進行Caesar密碼反向移位（移位-5或+21）'
        },
        {
            id: 27,
            category: 'crypto',
            title: 'Reverse Base64',
            description: '這段文字被反轉後再進行Base64編碼：\n密文：fTQ2ZXNhYl9lc3JldmVye0tIRlND\n提示：先Base64解碼，再反轉字符串',
            difficulty: 'hard',
            points: 45,
            hint: 'Base64解碼後得到的字符串需要反轉才能得到原始flag'
        },
        {
            id: 28,
            category: 'crypto',
            title: 'Polyalphabetic Cipher',
            description: '解碼這段多字母替換密碼：\n密文：JGSNU{oqbhbpgv_apqgfjyo}\n使用了兩個密鑰：第一個密鑰"HONGKONG"用於前半部分，第二個密鑰"CSFHK"用於後半部分\n提示：需要根據位置選擇正確的密鑰',
            difficulty: 'hard',
            points: 50,
            hint: '將密文分為兩半，前半部分使用HONGKONG密鑰，後半部分使用CSFHK密鑰進行維吉尼亞密碼解密'
        },
        {
            id: 29,
            category: 'reverse',
            title: 'Assembly Analysis',
            description: '以下是一段簡單的彙編代碼，分析其功能並找出flag：\nmov eax, 0x43\nmov ebx, 0x53\nmov ecx, 0x46\nmov edx, 0x48\nmov esi, 0x4B\n這些值對應ASCII字符，組合後加上格式即為flag',
            difficulty: 'hard',
            points: 55,
            hint: '0x43=C, 0x53=S, 0x46=F, 0x48=H, 0x4B=K，組合起來加上flag格式'
        },
        {
            id: 30,
            category: 'web',
            title: 'JavaScript Deobfuscation',
            description: '這段JavaScript代碼被嚴重混淆：\neval(String.fromCharCode(67,111,110,115,111,108,101,46,108,111,103,40,39,67,83,70,72,75,123,106,115,95,111,98,102,117,115,99,97,116,101,125,39,41,59))\n執行它並找到flag',
            difficulty: 'hard',
            points: 48,
            hint: 'String.fromCharCode將數字轉換為字符，執行後會在控制台輸出flag'
        },
        {
            id: 31,
            category: 'misc',
            title: 'Steganography Challenge',
            description: 'Flag隱藏在以下數據中：\n在瀏覽器控制台執行以下代碼來提取flag：\natob("Q1NGSEt7c3RlZ2Fub2dyYXBoeX0=")',
            difficulty: 'hard',
            points: 40,
            hint: '使用JavaScript的atob()函數解碼Base64字符串'
        }
    ];
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
        const res = await fetch(`discussions_api.php?action=view&id=${encodeURIComponent(id)}`);
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
                    <div class="form-group">
                        <label for="replyAuthor">您的名稱</label>
                        <input type="text" id="replyAuthor" required>
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
    
    const author = document.getElementById('replyAuthor');
    const content = document.getElementById('replyContent');
    
    if (!author || !content) {
        showNotification('表單元素未找到', 'error');
        return;
    }
    
    const authorValue = author.value.trim();
    const contentValue = content.value.trim();
    
    if (!authorValue || !contentValue) {
        showNotification('請填寫所有必填欄位', 'error');
        return;
    }
    
    fetch('discussions_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'reply',
            thread_id: thread.id,
            author: authorValue,
            content: contentValue
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
        fetch('discussions_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'delete', id })
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
                    const res = await fetch('discussions_api.php?action=list');
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
    const author = document.getElementById('postAuthor').value.trim();

    if (!title || !content || !author) {
        showNotification('請填寫所有必填欄位', 'error');
        return;
    }

    fetch('discussions_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'create',
            title,
            category,
            content,
            author
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
                const res = await fetch('discussions_api.php?action=list');
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


