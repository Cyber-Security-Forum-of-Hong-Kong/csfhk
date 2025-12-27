let ctfChallenges = [];
let completedChallenges = [];
let currentCTFCategory = null;

function initializeCTFStorage() {
    const completed = localStorage.getItem('csfhk_completed');
    if (completed) {
        completedChallenges = JSON.parse(completed);
    } else {
        completedChallenges = [];
        saveCompletedChallenges();
    }
}

function saveCompletedChallenges() {
    localStorage.setItem('csfhk_completed', JSON.stringify(completedChallenges));
}

function initializeCTFChallenges() {
    // All CTF challenges have been removed; keep structure for future use.
    ctfChallenges = [];
}

function getCTFCategoryName(category) {
    const categoryMap = {
        'web': 'Web Security',
        'crypto': 'Cryptography',
        'forensics': 'Digital Forensics',
        'reverse': 'Reverse Engineering',
        'pwn': 'Pwn / Exploitation',
        'misc': 'Miscellaneous'
    };
    return categoryMap[category] || category;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function backToCategories() {
    currentCTFCategory = null;
    const container = document.getElementById('challengesContainer');
    if (container) {
        container.innerHTML = '';
    }
    const challengesSection = document.getElementById('challenges');
    if (challengesSection) {
        challengesSection.style.display = 'none';
    }
    document.getElementById('ctf').scrollIntoView({ behavior: 'smooth' });
}

function renderCTFCategories() {
    const container = document.getElementById('challengesContainer');
    if (!container) return;
    
    container.innerHTML = '';
    
    updateStats();
}

function renderCTFChallenges() {
    const container = document.getElementById('challengesContainer');
    if (!container) return;
    
    const challenges = ctfChallenges.filter(c => c.category === currentCTFCategory);
    const categoryName = getCTFCategoryName(currentCTFCategory);
    
    const challengesSection = document.getElementById('challenges');
    if (challengesSection) {
        challengesSection.style.display = 'block';
    }
    
    container.innerHTML = `
        <div class="ctf-header-actions">
            <button class="btn-back" onclick="backToCategories()">← 返回分類</button>
        </div>
        <div class="ctf-challenges-container">
            <h3 class="ctf-category-title">${categoryName} 挑戰</h3>
            ${challenges.length === 0 ? '<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">此分類暫無挑戰題目</p>' : ''}
            ${challenges.map(challenge => createCTFChallengeElement(challenge)).join('')}
        </div>
    `;
    
    container.scrollIntoView({ behavior: 'smooth' });
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

function updateStats() {
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

function setupCTFSpecialFeatures() {
    window.getFlag = function() {
        console.log('CSFHK{console_master}');
        showNotification('請在控制台查看flag輸出', 'info');
    };
}

document.addEventListener('DOMContentLoaded', function() {
    initializeCTFStorage();
    initializeCTFChallenges();
    setupCTFSpecialFeatures();
    updateStats();
    
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', function() {
            const category = this.getAttribute('data-category');
            currentCTFCategory = category;
            renderCTFChallenges();
            document.getElementById('challenges').scrollIntoView({ behavior: 'smooth' });
        });
    });
});

