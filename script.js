let discussions = [];
let currentView = 'list';
let selectedThreadId = null;
let filterCategory = 'all';
let searchQuery = '';
let currentCTFCategory = null;
let ctfChallenges = [];
let completedChallenges = [];

function initializeStorage() {
    const stored = localStorage.getItem('csfhk_discussions');
    if (stored) {
        discussions = JSON.parse(stored);
    } else {
        discussions = [];
        saveToStorage();
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
    localStorage.setItem('csfhk_discussions', JSON.stringify(discussions));
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
            flag: 'CSFHK{hidden_in_source}',
            difficulty: 'easy',
            points: 10,
            hint: '右鍵點擊頁面，選擇"查看頁面源代碼"或按F12查看開發者工具'
        },
        {
            id: 2,
            category: 'web',
            title: 'Base64 Encode',
            description: '解碼這段Base64編碼的文字：Q1NGSEst{eG9uZ19rb25nX2ZvcnVt}',
            flag: 'CSFHK-{hong_kong_forum}',
            difficulty: 'easy',
            points: 10,
            hint: '使用在線Base64解碼工具或JavaScript atob()函數'
        },
        {
            id: 3,
            category: 'crypto',
            title: 'Caesar Cipher',
            description: '解碼這段凱撒密碼（移位3）：FVIKN{oryh_qhw_zrun}',
            flag: 'CSFHK{love_net_work}',
            difficulty: 'easy',
            points: 15,
            hint: '每個字母向前移動3個位置（解密時向後移動3個位置）'
        },
        {
            id: 4,
            category: 'crypto',
            title: 'ROT13 Challenge',
            description: '解碼這段ROT13編碼：PFSUX{uryyb_plorefrphevgl}',
            flag: 'CSFHK{hello_cybersecurity}',
            difficulty: 'easy',
            points: 15,
            hint: 'ROT13是每個字母移動13個位置的凱撒密碼（加密和解密相同）'
        },
        {
            id: 5,
            category: 'misc',
            title: 'Flag in Console',
            description: '打開瀏覽器的開發者工具（F12），在控制台（Console）中輸入：getFlag() 然後按Enter',
            flag: 'CSFHK{console_master}',
            difficulty: 'easy',
            points: 10,
            hint: '按F12打開開發者工具，切換到Console標籤，然後輸入getFlag()'
        },
        {
            id: 6,
            category: 'misc',
            title: 'Hexadecimal Decode',
            description: '將這段十六進制轉換為文字：435346484B7B6865785F6465636F64657D',
            flag: 'CSFHK{hex_decode}',
            difficulty: 'easy',
            points: 15,
            hint: '每兩個十六進制字符代表一個ASCII字符'
        },
        {
            id: 7,
            category: 'web',
            title: 'Cookie Challenge',
            description: '設置一個名為"secret_flag"的Cookie，值為"CSFHK-cookie_master"，然後重新加載頁面。提示：使用JavaScript document.cookie設置。',
            flag: 'CSFHK-cookie_master',
            difficulty: 'medium',
            points: 20,
            hint: '在控制台執行: document.cookie = "secret_flag=CSFHK-cookie_master"'
        },
        {
            id: 8,
            category: 'crypto',
            title: 'Binary to Text',
            description: '將這段二進制轉換為文字：01000011 01010011 01000110 01001000 01001011 01111011 01100010 01101001 01101110 01100001 01110010 01111001 01111101',
            flag: 'CSFHK{binary}',
            difficulty: 'medium',
            points: 20,
            hint: '每8位二進制數代表一個ASCII字符'
        },
        {
            id: 9,
            category: 'forensics',
            title: 'Image Metadata',
            description: '查看這張圖片的EXIF數據，flag在相機製造商字段中：data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPjx0ZXh0IHg9IjEwIiB5PSI1MCIgZm9udC1zaXplPSIyMCI+Q1NGSEst{eXhpZl9kYXRhfTwvdGV4dD48L3N2Zz4=',
            flag: 'CSFHK-{exif_data}',
            difficulty: 'medium',
            points: 25,
            hint: '某些圖片包含隱藏的元數據'
        },
        {
            id: 10,
            category: 'web',
            title: 'JavaScript Obfuscation',
            description: '這段JavaScript代碼被混淆了，你能執行它並找到flag嗎？在控制台執行：eval(atob("Y29uc29sZS5sb2coJ0NTRktILXtzY3JpcHRfb2JmdXNjYXRpb259Jyk7"))',
            flag: 'CSFHK-{script_obfuscation}',
            difficulty: 'medium',
            points: 25,
            hint: '在瀏覽器控制台中複製並運行這段代碼'
        },
        {
            id: 11,
            category: 'reverse',
            title: 'Packed XOR',
            description: '以下十六進制數據代表一段被XOR加密的文字，使用密鑰0x37解密並提交flag：525343465b0d445140155b0c5b1b414c4415595d0d17411b1b5a',
            flag: 'CSFHK{xor_reverse_master}',
            difficulty: 'hard',
            points: 40,
            hint: '將hex轉byte，每個byte與0x37異或後轉ASCII'
        },
        {
            id: 12,
            category: 'pwn',
            title: 'Format String Leak',
            description: '本題flag格式為CSFHK{format_string}. 典型printf("%s")漏洞可使用%s連續輸出棧內容，請推測flag並提交。',
            flag: 'CSFHK{format_string}',
            difficulty: 'hard',
            points: 45,
            hint: 'CTF常見格式化字符串利用，flag已知格式，可直接提交'
        },
        {
            id: 13,
            category: 'misc',
            title: 'Layered Base',
            description: '這串字串被連續三次Base64編碼，解開它：QzFHRUhLe0IxbmFyeV9iNHNlbTY0X2YwbjB0X2gxZDFuXzNOMV0=',
            flag: 'CSFHK{B1nary_b4se64_f0n0t_h1d1n_3N1]',
            difficulty: 'hard',
            points: 35,
            hint: '連續解三次Base64，注意大小寫與字元'
        },
        {
            id: 14,
            category: 'crypto',
            title: 'Vigenère Cipher',
            description: '解碼這段維吉尼亞密碼，密鑰是"HONGKONG"：\nJGSNU{jvmlbrxo_qvvose}',
            flag: 'CSFHK{vigenere_cipher}',
            difficulty: 'hard',
            points: 40,
            hint: '維吉尼亞密碼使用多字母替換，需要對每個字母使用對應的密鑰字母進行凱撒密碼解密'
        },
        {
            id: 15,
            category: 'crypto',
            title: 'Rail Fence Cipher',
            description: '解碼這段欄柵密碼（Rail Fence，3行）：\n讀取順序：第1行、第2行、第3行\n密文：C_K_LH_OK{_s_er_ou_g}\n按3行欄柵密碼規則重新排列',
            flag: 'CSFHK{secure_log}',
            difficulty: 'hard',
            points: 35,
            hint: '將字母按Z字形分3行排列：第1行(位置0,4,8...)，第2行(位置1,3,5...)，第3行(位置2,6,10...)'
        },
        {
            id: 16,
            category: 'crypto',
            title: 'Playfair Cipher',
            description: '解碼這段Playfair密碼，密鑰矩陣的關鍵詞是"CSFHK"：\n密文：QB QZ FB ZQ QF BF QZ QF\n提示：I和J視為同一字母',
            flag: 'CSFHK{playfair}',
            difficulty: 'hard',
            points: 45,
            hint: 'Playfair密碼使用5x5矩陣，將字母對進行替換'
        },
        {
            id: 17,
            category: 'crypto',
            title: 'Multi-Base Challenge',
            description: '這段文字經過Base64編碼：\nQ1NGSEt7bXVsdGliYXNlfQ==\n解碼後即可得到flag',
            flag: 'CSFHK{multibase}',
            difficulty: 'hard',
            points: 40,
            hint: '直接使用Base64解碼工具或JavaScript atob()函數'
        },
        {
            id: 18,
            category: 'crypto',
            title: 'RSA Mini Challenge',
            description: '這是一個簡化的RSA加密挑戰：\nn = 77, e = 7, c = 68\n求解明文m（答案轉換為字母，a=1, b=2...，然後轉換為flag格式）',
            flag: 'CSFHK{rsa}',
            difficulty: 'hard',
            points: 50,
            hint: 'n=77=7*11，phi(n)=60，計算私鑰d=43，然後用m = c^d mod n = 68^43 mod 77 = 19，對應字母s'
        },
        {
            id: 19,
            category: 'crypto',
            title: 'Substitution Cipher',
            description: '解碼這段簡單替換密碼：\nXZT YJH YJH QZR ZH YJH ZJHT ZQKFX\n提示：這是一個單字母替換密碼，分析字母頻率',
            flag: 'CSFHK{the_quick_brown}',
            difficulty: 'hard',
            points: 38,
            hint: '使用字母頻率分析，最常見的字母通常是E、T、A等'
        },
        {
            id: 20,
            category: 'crypto',
            title: 'XOR Cipher',
            description: '這段文字被XOR加密，密鑰是"HKG"（循環使用）：\n密文（hex）：0b180100003c30243517282e3823223a36\n提示：將hex轉換為bytes，每個byte與對應位置的密鑰byte（H=72, K=75, G=71）進行XOR',
            flag: 'CSFHK{xor_cipher}',
            difficulty: 'hard',
            points: 42,
            hint: '將hex轉換為bytes，每個byte與對應位置的密鑰byte進行XOR運算'
        },
        {
            id: 21,
            category: 'crypto',
            title: 'Affine Cipher',
            description: '解碼這段仿射密碼：\n密文：SUHRG{Lcipv_za_xcspyfz}\n加密公式：(a*x + b) mod 26，其中a=5, b=8\n提示：需要計算a的模逆元（5^(-1) mod 26 = 21）',
            flag: 'CSFHK{Learn_to_decrypt}',
            difficulty: 'hard',
            points: 43,
            hint: '解密公式：x = a^(-1) * (y - b) mod 26，其中a^(-1) = 21'
        },
        {
            id: 22,
            category: 'crypto',
            title: 'Multi-Layer Encoding',
            description: '這段文字經過三重編碼：\n1. Base64編碼\n2. 轉換為十六進制\n3. 字符串反轉\n密文（hex，已反轉）：9346d62607252326a65375a566a485a55364742666c67446376585267347543574e41315\n提示：需要按照相反順序解碼',
            flag: 'CSFHK{multi_layer_encoding}',
            difficulty: 'hard',
            points: 55,
            hint: '先將hex反轉，再hex轉ASCII，最後Base64解碼'
        },
        {
            id: 23,
            category: 'crypto',
            title: 'Double XOR Encryption',
            description: '這段文字被XOR加密了兩次：\n密文（hex）：4353464b4b7b6d766c74695c786f725c6368616f6c656e64657d\n第一次XOR密鑰：KEY1\n第二次XOR密鑰：KEY2\n提示：需要按照相反順序進行兩次XOR解密',
            flag: 'CSFHK{multi_xor_challenge}',
            difficulty: 'hard',
            points: 52,
            hint: '先用KEY2進行XOR，再用KEY1進行XOR（因為XOR是可逆的，順序不影響結果）'
        },
        {
            id: 24,
            category: 'crypto',
            title: 'Columnar Transposition',
            description: '解碼這段欄位換位密碼：\n密文：C{mtpiFoaasnHlrni}Ku_stScnroo\n換位密鑰：CSFHK\n提示：需要根據密鑰字母順序重新排列列',
            flag: 'CSFHK{columnar_transposition}',
            difficulty: 'hard',
            points: 48,
            hint: '將密文按列排列，然後根據密鑰字母順序重新排列列'
        },
        {
            id: 25,
            category: 'crypto',
            title: 'RSA Advanced',
            description: '這是一個更複雜的RSA挑戰：\nn = 323, e = 7, c = 18\n求解明文m（答案轉換為字母，a=1, b=2...，然後轉換為flag格式）\n提示：需要分解n並計算私鑰',
            flag: 'CSFHK{rsa_hard}',
            difficulty: 'hard',
            points: 60,
            hint: 'n=323=17*19，phi(n)=288，計算d=247，m = c^d mod n = 18^247 mod 323 = 18，對應字母r'
        },
        {
            id: 26,
            category: 'crypto',
            title: 'Combined Cipher',
            description: '這段文字使用了組合加密：\n1. 先進行Caesar密碼（移位5）\n2. 再進行XOR加密（密鑰：XOR）\n密文（hex）：101719151f29303b203f212132260d30212735252525\n提示：需要按照相反順序解密',
            flag: 'CSFHK{combined_cipher}',
            difficulty: 'hard',
            points: 58,
            hint: '先用XOR密鑰XOR解密，再進行Caesar密碼反向移位（移位-5或+21）'
        },
        {
            id: 27,
            category: 'crypto',
            title: 'Reverse Base64',
            description: '這段文字被反轉後再進行Base64編碼：\n密文：fTQ2ZXNhYl9lc3JldmVye0tIRlND\n提示：先Base64解碼，再反轉字符串',
            flag: 'CSFHK{reverse_base64}',
            difficulty: 'hard',
            points: 45,
            hint: 'Base64解碼後得到的字符串需要反轉才能得到原始flag'
        },
        {
            id: 28,
            category: 'crypto',
            title: 'Polyalphabetic Cipher',
            description: '解碼這段多字母替換密碼：\n密文：JGSNU{oqbhbpgv_apqgfjyo}\n使用了兩個密鑰：第一個密鑰"HONGKONG"用於前半部分，第二個密鑰"CSFHK"用於後半部分\n提示：需要根據位置選擇正確的密鑰',
            flag: 'CSFHK{advanced_vigenere}',
            difficulty: 'hard',
            points: 50,
            hint: '將密文分為兩半，前半部分使用HONGKONG密鑰，後半部分使用CSFHK密鑰進行維吉尼亞密碼解密'
        },
        {
            id: 29,
            category: 'reverse',
            title: 'Assembly Analysis',
            description: '以下是一段簡單的彙編代碼，分析其功能並找出flag：\nmov eax, 0x43\nmov ebx, 0x53\nmov ecx, 0x46\nmov edx, 0x48\nmov esi, 0x4B\n這些值對應ASCII字符，組合後加上格式即為flag',
            flag: 'CSFHK{assembly}',
            difficulty: 'hard',
            points: 55,
            hint: '0x43=C, 0x53=S, 0x46=F, 0x48=H, 0x4B=K，組合起來加上flag格式'
        },
        {
            id: 30,
            category: 'web',
            title: 'JavaScript Deobfuscation',
            description: '這段JavaScript代碼被嚴重混淆：\neval(String.fromCharCode(67,111,110,115,111,108,101,46,108,111,103,40,39,67,83,70,72,75,123,106,115,95,111,98,102,117,115,99,97,116,101,125,39,41,59))\n執行它並找到flag',
            flag: 'CSFHK{js_obfuscate}',
            difficulty: 'hard',
            points: 48,
            hint: 'String.fromCharCode將數字轉換為字符，執行後會在控制台輸出flag'
        },
        {
            id: 31,
            category: 'misc',
            title: 'Steganography Challenge',
            description: 'Flag隱藏在以下數據中：\n在瀏覽器控制台執行以下代碼來提取flag：\natob("Q1NGSEt7c3RlZ2Fub2dyYXBoeX0=")',
            flag: 'CSFHK{steganography}',
            difficulty: 'hard',
            points: 40,
            hint: '使用JavaScript的atob()函數解碼Base64字符串'
        }
    ];
}

function getNextId() {
    if (discussions.length === 0) return 1;
    return Math.max(...discussions.map(d => d.id)) + 1;
}

document.addEventListener('DOMContentLoaded', function() {
    initializeStorage();
    initializeCTFChallenges();
    setupCTFSpecialFeatures();
    renderDiscussions();
    setupEventListeners();
    animateTerminal();
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
    
    if (currentView === 'thread') {
        renderThreadDetail();
        return;
    }
    
    if (currentView === 'ctf') {
        renderCTFChallenges();
        return;
    }
    
    container.innerHTML = '';
    
    let filtered = discussions;
    
    if (filterCategory !== 'all') {
        filtered = filtered.filter(t => t.category === filterCategory);
    }
    
    if (searchQuery.trim()) {
        const query = searchQuery.toLowerCase();
        filtered = filtered.filter(t => 
            t.title.toLowerCase().includes(query) || 
            t.content.toLowerCase().includes(query) ||
            t.author.toLowerCase().includes(query)
        );
    }
    
    filtered.sort((a, b) => new Date(b.date + ' ' + b.time) - new Date(a.date + ' ' + a.time));
    
    if (filtered.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">暫無討論主題，成為第一個發表的人吧！</p>';
        return;
    }

    filtered.forEach(thread => {
        const threadElement = createThreadElement(thread);
        container.appendChild(threadElement);
    });
}

function createThreadElement(thread) {
    const threadDiv = document.createElement('div');
    threadDiv.className = 'thread-item';
    const repliesCount = thread.replies ? thread.replies.length : 0;
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

function viewThread(id) {
    selectedThreadId = id;
    currentView = 'thread';
    renderDiscussions();
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
            <h3 class="replies-title">回覆 (${replies.length})</h3>
            <div class="replies-container" id="repliesContainer">
                ${replies.map(reply => `
                    <div class="reply-item">
                        <div class="reply-header">
                            <strong>${escapeHtml(reply.author)}</strong>
                            <span class="reply-date">${reply.date} ${reply.time || ''}</span>
                        </div>
                        <div class="reply-content">${escapeHtml(reply.content).replace(/\n/g, '<br>')}</div>
                    </div>
                `).join('')}
            </div>
            <div class="reply-form-container">
                <h4>發表回覆</h4>
                <form id="replyForm" onsubmit="submitReply(event)">
                    <div class="form-group">
                        <label for="replyAuthor">您的名稱</label>
                        <input type="text" id="replyAuthor" required>
                    </div>
                    <div class="form-group">
                        <label for="replyContent">回覆內容</label>
                        <textarea id="replyContent" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit">發表回覆</button>
                </form>
            </div>
        </div>
    `;
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

function submitFlag(challengeId) {
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
    const userFlag = inputElement.value.trim();
    
    if (!userFlag) {
        showNotification('請輸入Flag', 'error');
        return;
    }
    
    const normalizedUserFlag = userFlag.toLowerCase().replace(/[-\s{}]/g, '');
    const normalizedCorrectFlag = challenge.flag.toLowerCase().replace(/[-\s{}]/g, '');
    
    if (normalizedUserFlag === normalizedCorrectFlag) {
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
}

function submitReply(event) {
    event.preventDefault();
    const thread = discussions.find(t => t.id === selectedThreadId);
    if (!thread) return;
    
    const author = document.getElementById('replyAuthor').value;
    const content = document.getElementById('replyContent').value;
    const now = new Date();
    
    if (!thread.replies) thread.replies = [];
    
    thread.replies.push({
        id: Date.now(),
        author: author,
        content: content,
        date: now.toISOString().split('T')[0],
        time: now.toTimeString().split(' ')[0].substring(0, 5)
    });
    
    saveToStorage();
    renderThreadDetail();
    document.getElementById('replyForm').reset();
    showNotification('回覆發布成功！', 'success');
}

function deleteThread(id, event) {
    event.stopPropagation();
    if (confirm('確定要刪除此主題嗎？')) {
        discussions = discussions.filter(t => t.id !== id);
        saveToStorage();
        renderDiscussions();
        showNotification('主題已刪除', 'success');
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
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth' });
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
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

    const now = new Date();
    const newThread = {
        id: getNextId(),
        title: title,
        category: category,
        content: content,
        author: author,
        date: now.toISOString().split('T')[0],
        time: now.toTimeString().split(' ')[0].substring(0, 5),
        replies: [],
        views: 0
    };

    discussions.unshift(newThread);
    saveToStorage();
    renderDiscussions();
    
    document.getElementById('newPostModal').classList.remove('active');
    document.getElementById('newPostForm').reset();
    
    showNotification('主題發布成功！', 'success');
    
    document.getElementById('discussions').scrollIntoView({ behavior: 'smooth' });
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






