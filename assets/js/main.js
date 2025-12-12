/**
 * 碳森林项目主JavaScript文件 - 修复版
 */

// 全局变量
let currentUser = null;
let isLoggedIn = false;

// DOM加载完成后执行
document.addEventListener('DOMContentLoaded', function() {
    // 检查用户登录状态
    checkLoginStatus();
    
    // 初始化页面特定功能
    initPageFunctions();
});

// API基础URL配置
const API_BASE_URL = window.location.origin + '/carbon_forest';

/**
 * 通用API请求函数
 */
function apiRequest(url, options = {}) {
    const token = localStorage.getItem('token');
    
    // 设置默认请求头
    const defaultHeaders = {
        'Content-Type': 'application/json',
    };
    
    // 如果存在token，添加Authorization头
    if (token) {
        defaultHeaders['Authorization'] = 'Bearer ' + token;
    }
    
    // 使用API_BASE_URL构建完整URL
    const fullUrl = API_BASE_URL + url;
    
    // 合并选项
    const requestOptions = {
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers
        }
    };
    
    return fetch(fullUrl, requestOptions)
        .then(response => {
            // 检查响应状态
            if (!response.ok) {
                console.error('API请求HTTP错误:', {
                    url: fullUrl,
                    status: response.status,
                    statusText: response.statusText
                });
                throw new Error('HTTP错误: ' + response.status + ' - ' + response.statusText);
            }
            
            // 检查响应内容类型
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                console.warn('API响应内容类型不是JSON:', contentType);
                // 尝试读取响应文本以获取更多信息
                return response.text().then(text => {
                    console.error('非JSON响应内容:', text.substring(0, 200));
                    throw new Error('服务器返回了非JSON格式的响应，可能是PHP错误或配置问题');
                });
            }
            
            return response.json();
        })
        .then(data => {
            if (!data) {
                throw new Error('API返回空数据');
            }
            
            // 记录成功的API调用
            console.log('API请求成功:', fullUrl, data);
            return data;
        })
        .catch(error => {
            console.error('API请求失败详情:', {
                url: fullUrl,
                message: error.message,
                stack: error.stack,
                token: token ? 'token存在' : 'token不存在'
            });
            
            // 重新抛出错误，让调用者处理
            throw error;
        });
}

/**
 * 获取当前页面文件名
 */
function getCurrentPageName() {
    const path = window.location.pathname;
    const segments = path.split('/');
    return segments[segments.length - 1];
}

/**
 * 检查用户登录状态
 */
function checkLoginStatus() {
    const token = localStorage.getItem('token');
    
    // 获取当前页面文件名
    const currentPage = getCurrentPageName();
    const isLoginPage = currentPage === 'login.html';
    const isRegisterPage = currentPage === 'register.html';
    const isIndexPage = currentPage === 'index.html';
    
    if (token) {
        // 如果是首页，延迟验证以避免跳转循环
        if (isIndexPage) {
            setTimeout(() => {
                validateTokenAndRedirect(token, isLoginPage, isRegisterPage, isIndexPage);
            }, 100);
        } else {
            validateTokenAndRedirect(token, isLoginPage, isRegisterPage, isIndexPage);
        }
    } else {
        // 如果没有token，跳转到登录页
        if (!isLoginPage && !isRegisterPage) {
            window.location.href = 'login.html';
        }
    }
}

/**
 * 验证token并处理跳转
 */
function validateTokenAndRedirect(token, isLoginPage, isRegisterPage, isIndexPage) {
    apiRequest('/api/user/profile.php')
    .then(data => {
        if (data.status === 'success') {
            isLoggedIn = true;
            currentUser = data.user;
            updateUserUI();
            
            // 如果当前在登录/注册页面且已登录，跳转到首页
            if (isLoginPage || isRegisterPage) {
                window.location.href = 'index.html';
            }
        } else {
            // Token无效，清除本地存储
            handleTokenInvalid(isLoginPage, isRegisterPage, isIndexPage);
        }
    })
    .catch(error => {
        console.error('检查登录状态失败:', error);
        // 如果是首页，不立即跳转，给用户一个缓冲时间
        if (isIndexPage) {
            console.log('首页token验证失败，但保持当前页面');
            // 可以在这里显示一个提示，但不强制跳转
        } else {
            handleTokenInvalid(isLoginPage, isRegisterPage, isIndexPage);
        }
    });
}

/**
 * 处理token无效的情况
 */
function handleTokenInvalid(isLoginPage, isRegisterPage, isIndexPage) {
    localStorage.removeItem('token');
    isLoggedIn = false;
    currentUser = null;
    
    // 如果不是登录/注册页面，跳转到登录页
    if (!isLoginPage && !isRegisterPage && !isIndexPage) {
        window.location.href = 'login.html';
    }
}

/**
 * 更新用户界面
 */
function updateUserUI() {
    // 更新头部用户信息
    const userAvatar = document.querySelector('.user-avatar');
    const userName = document.querySelector('.user-name');
    
    if (userAvatar && userName && currentUser) {
        userAvatar.src = currentUser.avatar ? 
            '../assets/uploads/' + currentUser.avatar : 
            '../assets/images/default_avatar.png';
        userName.textContent = currentUser.username;
    }
}

/**
 * 初始化页面特定功能
 */
function initPageFunctions() {
    // 根据当前页面执行不同的初始化
    const currentPage = getCurrentPageName();
    
    switch (currentPage) {
        case 'login.html':
            initLoginPage();
            break;
        case 'register.html':
            initRegisterPage();
            break;
        case 'index.html':
            initIndexPage();
            break;
        default:
            // 其他页面不执行特殊初始化
            break;
    }
}

/**
 * 初始化登录页面
 */
function initLoginPage() {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            // 表单验证
            if (!username || !password) {
                showToast('请填写用户名和密码', 'error');
                return;
            }
            
            // 发送登录请求（修正接口路径）
            apiRequest('/api/user/login.php', {  // 此处修改路径
                method: 'POST',
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            })
            .then(data => {
                if (data.status === 'success') {
                    localStorage.setItem('token', data.token);
                    isLoggedIn = true;
                    currentUser = data.user;
                    window.location.href = 'index.html';
                } else {
                    showToast(data.message || '登录失败', 'error');
                }
            })
            .catch(error => {
                console.error('登录失败:', error);
                showToast('登录失败，请稍后重试', 'error');
            });
        });
    }
}

/**
 * 验证用户名格式
 */
function validateUsername(username) {
    const usernameRegex = /^[a-zA-Z0-9_]{4,20}$/;
    return usernameRegex.test(username);
}

/**
 * 验证密码格式
 */
function validatePassword(password) {
    // 至少8位，包含大小写字母和数字
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    return passwordRegex.test(password);
}

/**
 * 验证邮箱格式
 */
function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * 初始化首页
 */
function initIndexPage() {
    // 加载能量球列表
    loadEnergyBalls();
    
    // 加载用户能量信息
    loadUserEnergy();
    
    // 加载任务列表
    loadTaskList();
}

/**
 * 初始化注册页面
 */
function initRegisterPage() {
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        // 添加实时验证
        const usernameEl = document.getElementById('username');
        const emailEl = document.getElementById('email');
        const passwordEl = document.getElementById('password');
        const confirmPasswordEl = document.getElementById('confirmPassword');
        
        if (usernameEl) {
            usernameEl.addEventListener('input', function() {
                const isValid = validateUsername(this.value);
                this.classList.toggle('is-invalid', !isValid && this.value.length > 0);
                this.classList.toggle('is-valid', isValid);
            });
        }
        
        if (passwordEl) {
            passwordEl.addEventListener('input', function() {
                const isValid = validatePassword(this.value);
                this.classList.toggle('is-invalid', !isValid && this.value.length > 0);
                this.classList.toggle('is-valid', isValid);
            });
        }
        
        if (confirmPasswordEl) {
            confirmPasswordEl.addEventListener('input', function() {
                const passwordValue = passwordEl ? passwordEl.value : '';
                const isValid = this.value === passwordValue && this.value.length > 0;
                this.classList.toggle('is-invalid', !isValid && this.value.length > 0);
                this.classList.toggle('is-valid', isValid);
            });
        }
        
        if (emailEl) {
            emailEl.addEventListener('input', function() {
                const isValid = validateEmail(this.value);
                this.classList.toggle('is-invalid', !isValid && this.value.length > 0);
                this.classList.toggle('is-valid', isValid);
            });
        }
        
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // 安全地获取表单元素值
            const usernameEl = document.getElementById('username');
            const emailEl = document.getElementById('email');
            const passwordEl = document.getElementById('password');
            const confirmPasswordEl = document.getElementById('confirmPassword');
            const userTypeEl = document.getElementById('userType');
            
            // 检查元素是否存在
            if (!usernameEl || !emailEl || !passwordEl || !confirmPasswordEl || !userTypeEl) {
                console.error('表单元素未找到');
                showToast('表单加载异常，请刷新页面重试', 'error');
                return;
            }
            
            const username = usernameEl.value.trim();
            const email = emailEl.value.trim();
            const password = passwordEl.value;
            const confirmPassword = confirmPasswordEl.value;
            const userType = userTypeEl.value;
            
            // 获取选中的载体类型
            const carrierType = document.querySelector('input[name="carrierType"]:checked')?.value || 'tree';
            
            // 前端表单验证
            if (!username || !email || !password || !confirmPassword) {
                showToast('请填写所有必填字段', 'error');
                return;
            }
            
            // 验证用户名格式
            if (!validateUsername(username)) {
                showToast('用户名格式错误：4-20位，只能包含字母、数字和下划线', 'error');
                usernameEl.focus();
                return;
            }
            
            // 验证邮箱格式
            if (!validateEmail(email)) {
                showToast('邮箱格式不正确，请输入有效的邮箱地址', 'error');
                emailEl.focus();
                return;
            }
            
            // 验证密码格式
            if (!validatePassword(password)) {
                showToast('密码格式错误：不少于8位，必须包含大小写字母和数字', 'error');
                passwordEl.focus();
                return;
            }
            
            if (password !== confirmPassword) {
                showToast('两次输入的密码不一致', 'error');
                confirmPasswordEl.focus();
                return;
            }
            
            // 发送注册请求（修正接口路径）
            apiRequest('/api/user/register.php', {  // 此处修改路径
                method: 'POST',
                body: JSON.stringify({
                    username: username,
                    email: email,
                    password: password,
                    user_type: userType,
                    carrier_type: carrierType
                })
            })
            .then(data => {
                if (data.status === 'success') {
                    showToast('注册成功，请登录', 'success');
                    // 跳转到登录页
                    setTimeout(() => {
                        window.location.href = 'login.html';
                    }, 2000);
                } else {
                    showToast(data.message || '注册失败', 'error');
                }
            })
            .catch(error => {
                console.error('注册失败:', error);
                showToast('注册失败，请稍后重试', 'error');
            });
        });
    }
}

/**
 * 加载用户碳账户信息
 */
function loadUserCarbonAccount() {
    console.log('=== 开始加载用户碳账户信息 ===');
    
    apiRequest('/api/user/profile.php')  // 修正为获取用户信息接口
    .then(data => {
        console.log('API响应数据:', data);
        
        if (data.status === 'success') {
            // 合并用户信息和能量信息
            const carbonAccount = {
                ...data.user,
                ...data.energy
            };
            console.log('合并后的碳账户数据:', carbonAccount);
            updateCarbonAccountUI(carbonAccount);
        } else {
            console.error('加载碳账户信息失败:', data.message);
        }
    })
    .catch(error => {
        console.error('加载碳账户信息失败:', error);
        console.error('错误详情:', error.message);
    });
}

/**
 * 加载虚拟载体信息
 */
function loadCarrierInfo() {
    apiRequest('/api/carrier/status.php')  // 修正为正确的PHP文件路径
    .then(data => {
        console.log('载体状态API响应数据:', data);
        updateCarrierUI(data);
    })
    .catch(error => {
        console.error('加载载体信息失败:', error);
    });
}

/**
 * 初始化首页
 */
function initIndexPage() {
    console.log('=== 开始初始化首页 ===');
    
    // 加载用户碳账户信息
    console.log('调用loadUserCarbonAccount()');
    loadUserCarbonAccount();
    
    // 加载虚拟载体信息
    console.log('调用loadCarrierInfo()');
    loadCarrierInfo();
    
    // 加载任务列表
    console.log('调用loadTaskList()');
    loadTaskList();
    
    // 加载能量球列表
    console.log('调用loadEnergyBalls()');
    loadEnergyBalls();
    
    // 更新用户界面信息
    console.log('调用updateIndexPageUI()');
    updateIndexPageUI();
    
    console.log('=== 首页初始化完成 ===');
}

/**
 * 更新碳账户界面
 */
function updateCarbonAccountUI(account) {
    console.log('=== 更新碳账户界面调试信息 ===');
    console.log('接收到的账户数据:', account);
    
    // 使用正确的选择器 - HTML中只有.energy-value类名
    const energyValue = document.querySelector('.energy-value');
    
    console.log('DOM元素状态:');
    console.log('energyValue元素:', energyValue);
    
    // 测试临时值 - 使用总能量或当前能量
    const testEnergy = account.total_energy || account.current_energy || 1530;
    
    console.log('将要设置的能量值:', testEnergy);
    
    if (energyValue) {
        energyValue.textContent = testEnergy;
        console.log('已设置energyValue为:', testEnergy);
        
        // 强制重绘
        energyValue.style.display = 'none';
        energyValue.offsetHeight; // 触发重排
        energyValue.style.display = '';
        console.log('强制重绘energyValue元素完成');
    } else {
        console.error('未找到energyValue元素！');
    }
    
    console.log('=== 更新碳账户界面完成 ===');
}

/**
 * 更新虚拟载体界面
 */
function updateCarrierUI(carrier) {
    console.log('=== 更新载体界面调试信息 ===');
    console.log('接收到的载体数据:', carrier);
    
    const carrierName = document.querySelector('.carrier-name');
    const carrierStage = document.querySelector('.carrier-description');
    const carrierImage = document.querySelector('.carrier-image');
    const progressFill = document.querySelector('.progress-fill');
    const progressText = document.querySelector('.progress-text');
    const nextStageName = document.querySelector('.next-stage-name');
    const energyRequired = document.querySelector('.energy-required span');
    
    console.log('DOM元素状态:');
    console.log('carrierName元素:', carrierName);
    console.log('progressFill元素:', progressFill);
    console.log('progressText元素:', progressText);
    
    if (carrierName) carrierName.textContent = carrier.carrier_name || '未命名载体';
    if (carrierStage) carrierStage.textContent = carrier.carrier_level ? `第${carrier.carrier_level}阶段` : '幼年期';
    
    // 根据载体阶段设置图片
    if (carrierImage && carrier.carrier_level) {
        // 阶段编号从1开始，图片编号从0开始，需要减1匹配
        const stageNumber = carrier.carrier_level - 1;
        const imagePath = `../assets/images/carrier_tree_${stageNumber}.png`;
        carrierImage.src = imagePath;
        carrierImage.alt = carrier.carrier_name || '虚拟载体';
        console.log('设置载体图片:', imagePath, '当前阶段:', carrier.carrier_level);
    }
    
    // 更新下一阶段信息
    if (nextStageName) {
        const nextStage = carrier.carrier_level ? carrier.carrier_level + 1 : 2;
        nextStageName.textContent = getStageName(nextStage);
    }
    
    // 更新下一阶段图片
    const nextStageImage = document.querySelector('.next-stage-image');
    if (nextStageImage && carrier.carrier_level) {
        // 下一阶段编号从当前阶段+1开始，图片编号从0开始，需要减1匹配
        const nextStageNumber = carrier.carrier_level; // 下一阶段的图片编号=下一阶段编号-1
        const imagePath = `../assets/images/carrier_tree_${nextStageNumber}.png`;
        nextStageImage.src = imagePath;
        nextStageImage.alt = getStageName(carrier.carrier_level + 1) || '下一阶段';
        console.log('设置下一阶段图片:', imagePath, '下一阶段:', carrier.carrier_level + 1);
    }
    
    // 更新所需能量
    if (energyRequired) {
        const requiredEnergy = carrier.next_level_energy || (carrier.next ? carrier.next.energy_required : 0) || 100;
        energyRequired.textContent = requiredEnergy;
    }
    
    // 更新最大能量限制显示
    const maxEnergySpan = document.querySelector('.max-energy');
    if (maxEnergySpan) {
        const maxEnergy = carrier.current_max_energy || 100;
        maxEnergySpan.textContent = maxEnergy;
    }
    
    // 更新进度条
    if (progressFill && progressText) {
        const currentEnergy = carrier.current_energy || 0;
        const maxEnergy = carrier.current_max_energy || 100;
        
        // 显示当前能量和最大能量限制
        progressFill.style.width = Math.min((currentEnergy / maxEnergy) * 100, 100) + '%';
        progressText.textContent = Math.floor(currentEnergy) + '/' + maxEnergy;
        
        console.log('进度条更新: 当前能量', currentEnergy, '最大能量', maxEnergy);
    }
    

    
    console.log('=== 更新载体界面完成 ===');
}

/**
 * 获取阶段名称
 */
function getStageName(stage) {
    const stageNames = {
        1: '种子',
        2: '小树苗',
        3: '大树',
        4: '森林',
    };
    return stageNames[stage] || '未知阶段';
}

/**
 * 升级虚拟载体
 */




/**
 * 浇水功能
 */
function waterCarrier() {
    console.log('=== 开始浇水 ===');
    
    // 显示加载状态
    const waterBtn = document.querySelector('.water-btn');
    if (waterBtn) {
        waterBtn.disabled = true;
        waterBtn.innerHTML = '<i class="fa fa-tint"></i> 浇水中...';
    }
    
    // 调用浇水API
    apiRequest('/api/energy/water.php', {
        method: 'POST'
    })
    .then(data => {
        console.log('浇水API响应:', data);
        
        if (data.status === 'success') {
            // 浇水成功
            showToast(data.message || '浇水成功！生成' + data.balls_generated + '个能量球', 'success');
            
            // 重新加载能量和载体信息
            setTimeout(() => {
                loadUserCarbonAccount();
                loadCarrierInfo();
            }, 1000);
            
            console.log('浇水成功');
        } else {
            // 浇水失败
            showToast('浇水失败: ' + data.message, 'error');
            console.error('浇水失败:', data.message);
        }
    })
    .catch(error => {
        console.error('浇水API调用失败:', error);
        showToast('浇水失败，请稍后重试', 'error');
    })
    .finally(() => {
        // 恢复按钮状态
        if (waterBtn) {
            waterBtn.disabled = false;
            waterBtn.innerHTML = '<i class="fa fa-tint"></i> 浇水';
        }
    });
}

/**
 * 显示提示信息
 */
function showToast(message, type = 'info') {
    // 创建提示元素
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    // 添加样式
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 4px;
        color: white;
        z-index: 1000;
        font-size: 14px;
        max-width: 300px;
        word-wrap: break-word;
    `;
    
    // 设置背景色
    switch (type) {
        case 'success':
            toast.style.backgroundColor = '#28a745';
            break;
        case 'error':
            toast.style.backgroundColor = '#dc3545';
            break;
        case 'warning':
            toast.style.backgroundColor = '#ffc107';
            toast.style.color = '#212529';
            break;
        default:
            toast.style.backgroundColor = '#17a2b8';
            break;
    }
    
    // 添加到页面
    document.body.appendChild(toast);
    
    // 3秒后自动移除
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 3000);
}

/**
 * 用户退出登录
 */
function logout() {
    localStorage.removeItem('token');
    isLoggedIn = false;
    currentUser = null;
    
    // 跳转到登录页
    window.location.href = 'login.html';
}

/**
 * 格式化数字（添加千分位分隔符）
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * 格式化日期
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

/**
 * 初始化能量球点击事件
 */
function initEnergyBalls() {
    const energyBalls = document.querySelectorAll('.energy-ball');
    
    energyBalls.forEach(ball => {
        // 移除之前的点击事件监听器，避免重复绑定
        ball.removeEventListener('click', handleEnergyBallClick);
        
        // 添加点击事件
        ball.addEventListener('click', handleEnergyBallClick);
        
        // 确保能量球可以点击
        ball.style.pointerEvents = 'auto';
        ball.style.cursor = 'pointer';
    });
}

/**
 * 处理能量球点击事件
 */
function handleEnergyBallClick(event) {
    const ball = event.currentTarget;
    const energyAmount = parseInt(ball.getAttribute('data-amount') || ball.textContent.replace('+', ''));
    const energyBallId = ball.getAttribute('data-ball-id'); // 获取能量球ID
    
    // 先检查当前能量状态，判断是否达到上限
    if (isEnergyAtMaxLimit()) {
        showToast('能量已达到当前阶段上限，请先升级载体才能继续收集能量', 'warning');
        return;
    }
    
    // 添加点击动画效果
    ball.style.transform = 'scale(1.2)';
    ball.style.opacity = '0.7';
    
    // 显示能量获取效果
    showEnergyGainEffect(ball, energyAmount);
    
    // 更新用户能量显示
    updateEnergyDisplay(energyAmount);
    
    // 移除能量球
    setTimeout(() => {
        ball.style.transition = 'all 0.5s ease';
        ball.style.transform = 'scale(0)';
        ball.style.opacity = '0';
        
        setTimeout(() => {
            if (ball.parentNode) {
                ball.parentNode.removeChild(ball);
            }
        }, 500);
    }, 300);
    
    // 发送API请求记录能量获取
    collectEnergyFromBall(energyAmount, energyBallId);
}

/**
 * 检查当前能量是否达到上限
 */
function isEnergyAtMaxLimit() {
    const energyValue = document.querySelector('.energy-value');
    const maxEnergySpan = document.querySelector('.max-energy');
    
    if (!energyValue || !maxEnergySpan) {
        console.warn('无法检查能量上限：缺少DOM元素');
        return false;
    }
    
    const currentEnergy = parseFloat(energyValue.textContent) || 0;
    const maxEnergy = parseFloat(maxEnergySpan.textContent) || 100;
    
    console.log('检查能量上限：当前能量', currentEnergy, '最大能量', maxEnergy);
    
    // 如果当前能量已经达到或超过最大能量限制，返回true
    return currentEnergy >= maxEnergy;
}

/**
 * 更新能量显示
 */
function updateEnergyDisplay(energyAmount) {
    const energyValue = document.querySelector('.energy-value');
    const maxEnergySpan = document.querySelector('.max-energy');
    
    if (energyValue && maxEnergySpan) {
        const currentEnergy = parseFloat(energyValue.textContent) || 0;
        const maxEnergy = parseFloat(maxEnergySpan.textContent) || 100;
        
        // 计算实际可增加的能量（不超过最大限制）
        const actualEnergyGain = Math.min(energyAmount, maxEnergy - currentEnergy);
        const newEnergy = currentEnergy + actualEnergyGain;
        
        energyValue.textContent = newEnergy.toFixed(2);
        
        // 更新进度条
        updateProgressBar(newEnergy);
        
        // 如果实际增加的能量小于点击的能量，说明达到了上限
        if (actualEnergyGain < energyAmount) {
            showToast(`能量已达到上限，只能增加${actualEnergyGain}点能量`, 'warning');
        }
    }
}

/**
 * 显示能量获取效果
 */
function showEnergyGainEffect(ball, energyAmount) {
    const effect = document.createElement('div');
    effect.className = 'energy-gain-effect';
    effect.textContent = `+${energyAmount}`;
    effect.style.cssText = `
        position: absolute;
        color: #4CAF50;
        font-weight: bold;
        font-size: 18px;
        pointer-events: none;
        z-index: 1000;
        animation: floatUp 1s ease-out forwards;
    `;
    
    // 获取能量球位置
    const ballRect = ball.getBoundingClientRect();
    effect.style.left = (ballRect.left + ballRect.width / 2) + 'px';
    effect.style.top = (ballRect.top + ballRect.height / 2) + 'px';
    
    document.body.appendChild(effect);
    
    // 动画结束后移除效果元素
    setTimeout(() => {
        if (effect.parentNode) {
            effect.parentNode.removeChild(effect);
        }
    }, 1000);
}

/**
 * 更新能量显示
 */
function updateEnergyDisplay(energyAmount) {
    const energyValue = document.querySelector('.energy-value');
    if (energyValue) {
        const currentEnergy = parseInt(energyValue.textContent) || 0;
        const newEnergy = currentEnergy + energyAmount;
        energyValue.textContent = newEnergy;
        
        // 更新进度条
        updateProgressBar(newEnergy);
    }
}

/**
 * 更新进度条显示
 * @param {number} currentEnergy 当前能量值
 */
function updateProgressBar(currentEnergy) {
    // 直接检查是否有任何尝试访问.upgrade-btn元素的操作
    // 我们将使用try-catch块来捕获任何可能的错误
    try {
        console.log('开始更新进度条，当前能量:', currentEnergy);
        
        const progressFill = document.querySelector('.progress-fill');
        const progressText = document.querySelector('.progress-text');
        const maxEnergySpan = document.querySelector('.max-energy');
        
        console.log('进度条填充元素:', progressFill);
        console.log('进度条文本元素:', progressText);
        console.log('最大能量元素:', maxEnergySpan);
        
        // 检查所有必要的元素是否存在
        if (!maxEnergySpan) {
            console.error('未找到.max-energy元素');
            return;
        }
        
        // 使用当前阶段的最大能量限制，而不是动态计算下一阶段
        const maxEnergy = parseFloat(maxEnergySpan.textContent) || 100;
        console.log('当前阶段最大能量限制:', maxEnergy);
        
        // 确保当前能量不超过最大限制
        const actualEnergy = Math.min(currentEnergy, maxEnergy);
        console.log('实际显示能量:', actualEnergy);
        
        const progressPercentage = Math.min((actualEnergy / maxEnergy) * 100, 100);
        console.log('进度百分比:', progressPercentage);
        
        if (progressFill) {
            console.log('更新进度条宽度:', progressPercentage + '%');
            progressFill.style.width = progressPercentage + '%';
        } else {
            console.error('未找到.progress-fill元素');
            const allProgressElements = document.querySelectorAll('[class*="progress"]');
            console.log('所有包含progress的类名元素:', allProgressElements);
        }
        
        if (progressText) {
            console.log('更新进度条文本:', Math.floor(actualEnergy) + '/' + maxEnergy);
            progressText.textContent = Math.floor(actualEnergy) + '/' + maxEnergy;
        } else {
            console.error('未找到.progress-text元素');
        }
        
        // 检查是否有.upgrade-btn元素，并在尝试访问它之前进行检查
        const upgradeBtn = document.querySelector('.upgrade-btn');
        if (upgradeBtn) {
            // 如果元素存在，我们可以在这里添加任何必要的操作
            console.log('找到.upgrade-btn元素:', upgradeBtn);
        } else {
            // 如果元素不存在，我们可以忽略它，或者添加一个警告
            console.warn('未找到.upgrade-btn元素，但这不会影响进度条的更新');
        }
    } catch (error) {
        console.error('更新进度条时发生错误:', error);
        console.error('错误堆栈:', error.stack);
    }
    

}

/**
 * 加载能量球列表
 */
function loadEnergyBalls() {
    apiRequest('/api/energy/list.php', {
        method: 'GET'
    })
    .then(data => {
        if (data.status === 'success') {
            console.log('能量球列表加载成功，数量:', data.count);
            renderEnergyBalls(data.energy_balls);
        } else {
            console.error('能量球列表加载失败:', data.message);
            // 如果API失败，使用默认的能量球
            renderDefaultEnergyBalls();
        }
    })
    .catch(error => {
        console.error('能量球列表API错误:', error);
        // 如果API错误，使用默认的能量球
        renderDefaultEnergyBalls();
    });
}

/**
 * 渲染能量球列表
 */
function renderEnergyBalls(energyBalls) {
    const energyBallsContainer = document.querySelector('.energy-balls');
    if (!energyBallsContainer) return;
    
    // 清空容器
    energyBallsContainer.innerHTML = '';
    
    // 渲染每个能量球
    energyBalls.forEach(ball => {
        const energyBall = document.createElement('div');
        energyBall.className = 'energy-ball';
        energyBall.setAttribute('data-amount', ball.energy_amount);
        energyBall.setAttribute('data-ball-id', ball.ball_id); // 添加能量球ID
        
        // 设置随机位置
        const left = Math.random() * 80 + 10; // 10% - 90%
        const top = Math.random() * 80 + 10; // 10% - 90%
        energyBall.style.left = left + '%';
        energyBall.style.top = top + '%';
        
        energyBall.textContent = '+' + ball.energy_amount;
        
        energyBallsContainer.appendChild(energyBall);
    });
    
    // 初始化能量球点击事件
    initEnergyBalls();
}

/**
 * 渲染默认能量球（API失败时使用）
 */
function renderDefaultEnergyBalls() {
    const energyBallsContainer = document.querySelector('.energy-balls');
    if (!energyBallsContainer) return;
    
    // 使用默认的能量球数据
    const defaultBalls = [
        { id: 'default_1', energy_amount: 5, left: 20, top: 30 },
        { id: 'default_2', energy_amount: 8, left: 70, top: 40 },
        { id: 'default_3', energy_amount: 3, left: 40, top: 70 }
    ];
    
    // 清空容器
    energyBallsContainer.innerHTML = '';
    
    // 渲染默认能量球
    defaultBalls.forEach(ball => {
        const energyBall = document.createElement('div');
        energyBall.className = 'energy-ball';
        energyBall.setAttribute('data-amount', ball.energy_amount);
        energyBall.setAttribute('data-ball-id', ball.id);
        energyBall.style.left = ball.left + '%';
        energyBall.style.top = ball.top + '%';
        energyBall.textContent = '+' + ball.energy_amount;
        
        energyBallsContainer.appendChild(energyBall);
    });
    
    // 初始化能量球点击事件
    initEnergyBalls();
}

/**
 * 加载用户能量信息
 */
function loadUserEnergy() {
    console.log('开始加载用户能量信息...');
    
    apiRequest('/api/user/energy.php', {
        method: 'GET'
    })
    .then(data => {
        if (data.status === 'success') {
            console.log('用户能量信息加载成功:', data);
            updateEnergyDisplay(data.current_energy || 0);
        } else {
            console.error('用户能量信息加载失败:', data.message);
            // 如果API失败，显示默认值0
            updateEnergyDisplay(0);
        }
    })
    .catch(error => {
        console.error('用户能量信息API错误:', error);
        // 如果API错误，显示默认值0
        updateEnergyDisplay(0);
    });
}

/**
 * 浇水功能
 */
function waterCarrier() {
    const waterBtn = document.querySelector('.water-btn');
    
    // 禁用按钮防止重复点击
    if (waterBtn) waterBtn.disabled = true;
    
    // 显示浇水动画
    showWateringAnimation();
    
    apiRequest('/api/energy/water.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'water'
        })
    })
    .then(data => {
        console.log('浇水API响应:', data);
        
        if (data.status === 'success') {
            // 浇水成功
            showWateringSuccess(data.message);
            
            // 重新加载能量球列表
            setTimeout(() => {
                loadEnergyBalls();
                loadUserEnergy();
            }, 1000);
            
        } else {
            // 浇水失败
            showWateringError(data.message || '浇水失败');
        }
    })
    .catch(error => {
        console.error('浇水API错误:', error);
        showWateringError('网络错误，请稍后重试');
    })
    .finally(() => {
        // 重新启用按钮
        if (waterBtn) setTimeout(() => { waterBtn.disabled = false; }, 3000);
    });
}

/**
 * 显示浇水动画
 */
function showWateringAnimation() {
    const carrierSection = document.querySelector('.carrier-section');
    if (!carrierSection) return;
    
    // 创建浇水动画元素
    const waterEffect = document.createElement('div');
    waterEffect.className = 'water-effect';
    waterEffect.innerHTML = '<i class="fa fa-tint" style="font-size: 48px; color: #2196F3;"></i>';
    
    // 设置样式
    waterEffect.style.cssText = `
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1000;
        animation: waterDrop 1.5s ease-in-out;
    `;
    
    carrierSection.appendChild(waterEffect);
    
    // 动画结束后移除元素
    setTimeout(() => {
        if (waterEffect.parentNode) {
            waterEffect.parentNode.removeChild(waterEffect);
        }
    }, 1500);
}

/**
 * 显示浇水成功提示
 */
function showWateringSuccess(message) {
    showNotification(message || '浇水成功！能量球已生成', 'success');
}

/**
 * 显示浇水错误提示
 */
function showWateringError(message) {
    showNotification(message || '浇水失败，请稍后重试', 'error');
}

/**
 * 显示通知
 */
function showNotification(message, type = 'info') {
    // 创建通知元素
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    // 设置样式
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background-color: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#F44336' : '#2196F3'};
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease-out;
        max-width: 300px;
    `;
    
    document.body.appendChild(notification);
    
    // 3秒后自动移除
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

/**
 * 收集能量球API请求
 */
function collectEnergyFromBall(energyAmount, energyBallId) {
    apiRequest('/api/energy/collect.php', {
        method: 'POST',
        body: JSON.stringify({
            energy_ball_id: energyBallId, // 添加能量球ID
            energy_amount: energyAmount,
            source: '能量球收集'
        })
    })
    .then(data => {
        if (data.status === 'success') {
            console.log('能量收集成功:', energyAmount);
            
            // 检查是否发生了载体升级
            if (data.carrier_upgraded) {
                console.log('载体升级成功，准备刷新页面');
                
                // 显示升级成功提示
                showToast('载体升级成功！页面即将刷新以显示新的载体阶段', 'success');
                
                // 延迟刷新页面，让用户看到提示
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                // 重新加载用户能量信息，确保显示正确
                loadUserEnergy();
            }
            
        } else if (data.message && data.message.includes('上限') || data.message.includes('最大') || data.message.includes('满')) {
            // 处理能量上限错误
            console.warn('能量收集失败（达到上限）:', data.message);
            showToast(data.message || '能量已达到上限，无法继续收集', 'warning');
            
            // 重新加载用户能量信息，确保显示正确
            loadUserEnergy();
            
        } else {
            console.error('能量收集失败:', data.message);
            showToast('能量收集失败: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('能量收集API错误:', error);
        showToast('网络错误，请稍后重试', 'error');
    });
}

/**
 * 加载任务列表
 */
function loadTaskList() {
    const taskListContainer = document.querySelector('.task-list');
    if (!taskListContainer) return;
    
    // 显示加载状态
    taskListContainer.innerHTML = '<div class="loading">加载中...</div>';
    
    // 使用统一的API请求函数
    apiRequest('/api/task/list.php', {
        method: 'GET'
    })
    .then(data => {
        if (data.status === 'success') {
            console.log('任务列表API响应:', data);
            console.log('任务数量:', data.tasks ? data.tasks.length : 0);
            console.log('调试信息:', data.debug);
            console.log('任务列表加载成功，任务数量:', data.tasks ? data.tasks.length : 0);
            renderTaskList(data.tasks);
            updateTaskStats(data.stats);
        } else {
            throw new Error(data.message || '获取任务列表失败，状态: ' + data.status);
        }
    })
    .catch(error => {
        console.error('任务列表API错误详情:', {
            message: error.message,
            stack: error.stack
        });
        
        let errorMessage = '加载任务列表失败: ' + error.message;
        
        // 根据错误类型提供更具体的提示
        if (error.message.includes('HTTP错误: 401')) {
            errorMessage = '身份验证失败，请重新登录';
        } else if (error.message.includes('HTTP错误: 404')) {
            errorMessage = 'API接口不存在，请检查服务器配置';
        } else if (error.message.includes('HTTP错误: 500')) {
            errorMessage = '服务器内部错误，请联系管理员';
        } else if (error.message.includes('非JSON格式')) {
            errorMessage = '服务器返回了错误页面，可能是PHP配置问题';
        }
        
        taskListContainer.innerHTML = '<div class="error">' + errorMessage + '</div>';
    });
}

/**
 * 渲染任务列表
 */
function renderTaskList(tasks) {
    const taskList = document.querySelector('.task-list');
    if (!taskList) return;
    
    // 清空现有任务列表
    taskList.innerHTML = '';
    
    if (!tasks || tasks.length === 0) {
        taskList.innerHTML = '<div class="no-tasks">暂无任务</div>';
        return;
    }
    
    tasks.forEach(task => {
        const taskItem = createTaskItem(task);
        taskList.appendChild(taskItem);
    });
    
    // 初始化任务按钮事件
    initTaskButtons();
}

/**
 * 创建任务项
 */
function createTaskItem(task) {
    const taskItem = document.createElement('div');
    taskItem.className = 'task-item';
    
    // 根据任务状态设置样式
    const isCompleted = task.user_status === '已完成';
    const isAvailable = task.available;
    
    if (isCompleted) {
        taskItem.classList.add('completed');
    }
    
    // 设置按钮文本和状态
    let buttonText, buttonDisabled, buttonStyle, taskItemStyle;
    
    if (isCompleted) {
        // 已完成的任务：绿色可点击状态
        buttonText = '已完成';
        buttonDisabled = false;
        buttonStyle = 'background-color: #28a745; color: white; cursor: pointer;';
        taskItemStyle = 'opacity: 0.7; background-color: #f8f9fa;';
    } else if (isAvailable) {
        // 可执行的任务：正常状态
        buttonText = '完成';
        buttonDisabled = false;
        buttonStyle = 'background-color: #007bff; color: white; cursor: pointer;';
        taskItemStyle = '';
    } else {
        // 不可执行的任务：灰色不可点击状态
        buttonText = '不可用';
        buttonDisabled = true;
        buttonStyle = 'background-color: #6c757d; color: white; cursor: not-allowed;';
        taskItemStyle = 'opacity: 0.5; background-color: #f8f9fa;';
    }
    
    // 设置任务项样式
    if (taskItemStyle) {
        taskItem.style.cssText = taskItemStyle;
    }
    
    taskItem.innerHTML = `
            <div class="task-info">
                <h4 class="task-name">${task.task_name}</h4>
                <p class="task-description">${task.description || ''}</p>
                <div class="task-reward">+${task.energy_reward} 能量</div>
                ${!isAvailable && !isCompleted ? '<div class="task-unavailable-reason">前置条件未满足</div>' : ''}
            </div>
            <button class="task-btn" data-task-id="${task.task_id}" ${buttonDisabled ? 'disabled' : ''} style="${buttonStyle}">
                ${buttonText}
            </button>
        `;
    
    return taskItem;
}

/**
 * 更新任务统计
 */
function updateTaskStats(stats) {
    if (!stats) return;
    
    // 更新任务统计信息
    const taskStatsElement = document.querySelector('.task-stats');
    if (taskStatsElement) {
        taskStatsElement.innerHTML = `
            <div class="stat-item">
                <span class="stat-label">总任务数:</span>
                <span class="stat-value">${stats.total_tasks || 0}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">已完成:</span>
                <span class="stat-value">${stats.completed_tasks || 0}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">今日完成:</span>
                <span class="stat-value">${stats.today_tasks || 0}</span>
            </div>
        `;
    }
}

/**
 * 初始化任务按钮事件
 */
function initTaskButtons() {
    const taskButtons = document.querySelectorAll('.task-btn');
    
    taskButtons.forEach(button => {
        // 移除之前的点击事件监听器，避免重复绑定
        button.removeEventListener('click', handleTaskComplete);
        
        // 只对可点击的按钮添加点击事件（非禁用状态且不是"已完成"状态）
        if (!button.disabled && button.textContent !== '已完成') {
            button.addEventListener('click', handleTaskComplete);
            button.style.cursor = 'pointer';
        } else if (button.textContent === '已完成') {
            // 已完成的任务按钮显示为绿色，但不绑定点击事件
            button.style.cursor = 'default';
        } else {
            // 不可用的任务按钮显示为灰色，不可点击
            button.style.cursor = 'not-allowed';
        }
    });
}

/**
 * 处理任务完成事件
 */
function handleTaskComplete(event) {
    const button = event.currentTarget;
    const taskItem = button.closest('.task-item');
    const taskId = parseInt(button.getAttribute('data-task-id')) || 0;
    
    // 防止重复点击
    if (button.disabled) return;
    
    button.disabled = true;
    button.textContent = '已完成';
    button.style.backgroundColor = '#ccc';
    button.style.cursor = 'default';
    
    // 获取任务奖励
    const rewardText = taskItem?.querySelector('.task-reward')?.textContent || '+0 能量';
    const energyReward = parseInt(rewardText.match(/\+(\d+)/)?.[1]) || 0;
    
    // 显示任务完成效果
    showTaskCompleteEffect(taskItem, energyReward);
    
    // 更新能量显示
    updateEnergyDisplay(energyReward);
    
    // 发送API请求完成任务
    completeTaskAPI(taskId, energyReward);
}

/**
 * 显示任务完成效果
 */
function showTaskCompleteEffect(taskItem, energyReward) {
    const effect = document.createElement('div');
    effect.className = 'task-complete-effect';
    effect.textContent = `任务完成！+${energyReward}能量`;
    effect.style.cssText = `
        position: relative;
        color: #4CAF50;
        font-weight: bold;
        font-size: 14px;
        margin-top: 5px;
        padding: 5px 10px;
        background-color: #E8F5E9;
        border-radius: 5px;
        animation: fadeInOut 2s ease-in-out forwards;
    `;
    
    taskItem.appendChild(effect);
    
    // 动画结束后移除效果元素
    setTimeout(() => {
        if (effect.parentNode) {
            effect.parentNode.removeChild(effect);
        }
    }, 2000);
}

/**
 * 完成任务API请求
 */
function completeTaskAPI(taskId, energyReward) {
    apiRequest('/api/task/complete.php', {
        method: 'POST',
        body: JSON.stringify({
            task_id: taskId
        })
    })
    .then(data => {
        if (data.status === 'success') {
            console.log('任务完成成功:', taskId);
        } else {
            console.error('任务完成失败:', data.message);
            // 如果API失败，恢复按钮状态
            const button = document.querySelector(`.task-btn[data-task-id="${taskId}"]`);
            if (button) {
                button.disabled = false;
                button.textContent = '完成';
                button.style.backgroundColor = '';
                button.style.cursor = 'pointer';
            }
        }
    })
    .catch(error => {
        console.error('任务完成API错误:', error);
        // 如果API错误，恢复按钮状态
        const button = document.querySelector(`.task-btn[data-task-id="${taskId}"]`);
        if (button) {
            button.disabled = false;
            button.textContent = '完成';
            button.style.backgroundColor = '';
            button.style.cursor = 'pointer';
        }
    });
}

/**
 * 加载用户能量信息
 */
function loadUserEnergy() {
    apiRequest('/api/user/energy.php', {
        method: 'GET'
    })
    .then(data => {
        if (data.status === 'success') {
            console.log('用户能量信息加载成功:', data);
            updateEnergyDisplay(data.current_energy || 0);
        } else {
            console.error('用户能量信息加载失败:', data.message);
            // 如果API失败，显示默认值0
            updateEnergyDisplay(0);
        }
    })
    .catch(error => {
        console.error('用户能量信息API错误:', error);
        // 如果API错误，显示默认值0
        updateEnergyDisplay(0);
    });
}

/**
 * 更新首页界面信息
 */
function updateIndexPageUI() {
    // 更新用户信息
    if (currentUser) {
        const userNameElements = document.querySelectorAll('.forest-user-details h3, .user-name');
        userNameElements.forEach(element => {
            element.textContent = currentUser.username;
        });
        
        const userTypeElement = document.querySelector('.forest-user-details p');
        if (userTypeElement) {
            userTypeElement.textContent = currentUser.user_type === 'personal' ? '个人用户' : '企业用户';
        }
    }
    
    // 加载用户能量信息
    loadUserEnergy();
}



// 添加CSS动画样式
const style = document.createElement('style');
style.textContent = `
    @keyframes floatUp {
        0% {
            transform: translateY(0);
            opacity: 1;
        }
        100% {
            transform: translateY(-50px);
            opacity: 0;
        }
    }
    
    @keyframes fadeInOut {
        0% {
            opacity: 0;
            transform: translateY(10px);
        }
        50% {
            opacity: 1;
            transform: translateY(0);
        }
        100% {
            opacity: 0;
            transform: translateY(-10px);
        }
    }
    
    .energy-gain-effect {
        animation: floatUp 1s ease-out forwards;
    }
    
    .task-complete-effect {
        animation: fadeInOut 2s ease-in-out forwards;
    }
`;
document.head.appendChild(style);