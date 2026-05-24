const API_BASE = '/api';

let state = {
    token: localStorage.getItem('token') || '',
    user: null,
    devices: [],
    scenes: [],
    automations: [],
    families: [],
    rooms: [],
    logs: [],
    alerts: [],
    currentPage: 'dashboard',
    currentFamily: '',
    wsConnected: false,
};

const typeIcons = {
    light: '💡',
    switch: '🔌',
    outlet: '⚡',
    thermostat: '🌡️',
    sensor_motion: '🚶',
    sensor_door: '🚪',
    sensor_temp: '🌡️',
    camera: '📹',
    lock: '🔒',
    fan: '🌀',
    curtain: '🪟',
    speaker: '🔊',
    default: '📱'
};

const typeNames = {
    light: '智能灯',
    switch: '智能开关',
    outlet: '智能插座',
    thermostat: '温控器',
    sensor_motion: '人体传感器',
    sensor_door: '门窗传感器',
    sensor_temp: '温湿度传感器',
    camera: '智能摄像头',
    lock: '智能门锁',
    fan: '智能风扇',
    curtain: '智能窗帘',
    speaker: '智能音箱'
};

const sceneIcons = {
    home: '🏠',
    walk: '🚶',
    film: '🎬',
    moon: '🌙',
    sun: '☀️',
    party: '🎉',
    scene: '🎭',
    book: '📚',
    game: '🎮',
    music: '🎵'
};

async function api(url, options = {}) {
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };

    if (state.token) {
        headers['Authorization'] = `Bearer ${state.token}`;
    }

    const response = await fetch(`${API_BASE}${url}`, {
        ...options,
        headers
    });

    const data = await response.json();

    if (response.status === 401) {
        logout();
        throw new Error('未授权');
    }

    return data;
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleString('zh-CN', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatFullTime(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleString('zh-CN');
}

async function login(username, password) {
    try {
        const result = await api('/auth/login', {
            method: 'POST',
            body: JSON.stringify({ username, password })
        });

        if (result.code === 0) {
            state.token = result.data.token;
            state.user = result.data.user;
            localStorage.setItem('token', state.token);
            localStorage.setItem('user', JSON.stringify(state.user));
            showMainPage();
            showToast('登录成功', 'success');
            initWebSocket();
            loadDashboard();
        } else {
            showToast(result.message || '登录失败', 'error');
        }
    } catch (error) {
        showToast('登录失败，请重试', 'error');
    }
}

async function register(username, email, password) {
    try {
        const result = await api('/auth/register', {
            method: 'POST',
            body: JSON.stringify({ username, email, password })
        });

        if (result.code === 0) {
            state.token = result.data.token;
            state.user = result.data.user;
            localStorage.setItem('token', state.token);
            localStorage.setItem('user', JSON.stringify(state.user));
            showMainPage();
            showToast('注册成功', 'success');
            loadDashboard();
        } else {
            showToast(result.message || '注册失败', 'error');
        }
    } catch (error) {
        showToast('注册失败，请重试', 'error');
    }
}

function logout() {
    state.token = '';
    state.user = null;
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    showLoginPage();
    showToast('已退出登录', 'info');
}

function showLoginPage() {
    document.getElementById('login-page').classList.add('active');
    document.getElementById('main-page').classList.remove('active');
}

function showMainPage() {
    document.getElementById('login-page').classList.remove('active');
    document.getElementById('main-page').classList.add('active');

    if (state.user) {
        document.getElementById('user-name').textContent = state.user.real_name || state.user.username;
    }
}

function showPage(pageName) {
    state.currentPage = pageName;

    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
        if (item.dataset.page === pageName) {
            item.classList.add('active');
        }
    });

    document.querySelectorAll('.page-content').forEach(content => {
        content.classList.remove('active');
    });

    const content = document.querySelector(`[data-content="${pageName}"]`);
    if (content) {
        content.classList.add('active');
    }

    const titles = {
        dashboard: '仪表盘',
        devices: '设备管理',
        scenes: '场景模式',
        automations: '自动化规则',
        logs: '日志记录',
        alerts: '告警中心',
        settings: '系统设置'
    };
    document.getElementById('page-title').textContent = titles[pageName] || '';

    loadPageContent(pageName);
}

async function loadPageContent(pageName) {
    switch (pageName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'devices':
            loadDevices();
            break;
        case 'scenes':
            loadScenes();
            break;
        case 'automations':
            loadAutomations();
            break;
        case 'logs':
            loadLogs();
            break;
        case 'alerts':
            loadAlerts();
            break;
        case 'settings':
            loadSettings();
            break;
    }
}

async function loadDashboard() {
    try {
        const result = await api('/system/dashboard');
        if (result.code === 0 && result.data) {
            const data = result.data;
            document.getElementById('stat-devices').textContent = data.total_devices;
            document.getElementById('stat-online').textContent = data.online_devices;
            document.getElementById('stat-scenes').textContent = data.total_scenes;
            document.getElementById('stat-alerts').textContent = data.unread_alerts;

            loadQuickScenes();
            loadRecentLogs(data.recent_logs || []);
            loadDashboardDevices();
        }
    } catch (error) {
        console.error('加载仪表盘失败:', error);
    }
}

async function loadQuickScenes() {
    try {
        const result = await api('/scenes');
        if (result.code === 0) {
            state.scenes = result.data;
            renderQuickScenes(state.scenes.filter(s => s.is_favorite).slice(0, 8));
        }
    } catch (error) {
        console.error('加载场景失败:', error);
    }
}

function renderQuickScenes(scenes) {
    const container = document.getElementById('quick-scenes');

    if (scenes.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="icon">🎬</div><p>暂无收藏场景</p></div>';
        return;
    }

    container.innerHTML = scenes.map(scene => `
        <button class="quick-scene-btn" onclick="executeScene(${scene.id})">
            <span class="icon">${sceneIcons[scene.icon] || '🎭'}</span>
            <span class="name">${scene.name}</span>
        </button>
    `).join('');
}

function loadRecentLogs(logs) {
    const container = document.getElementById('recent-logs');

    if (logs.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="icon">📋</div><p>暂无日志</p></div>';
        return;
    }

    container.innerHTML = logs.slice(0, 10).map(log => `
        <div class="log-item">
            <span class="log-time">${formatTime(log.created_at)}</span>
            <span class="log-level ${log.level}">${log.level.toUpperCase()}</span>
            <span class="log-message">${log.message}</span>
        </div>
    `).join('');
}

async function loadDashboardDevices() {
    try {
        const result = await api('/devices');
        if (result.code === 0) {
            state.devices = result.data;
            renderDashboardDevices(state.devices);
        }
    } catch (error) {
        console.error('加载设备失败:', error);
    }
}

function renderDashboardDevices(devices) {
    const container = document.getElementById('dashboard-devices');

    if (devices.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="icon">💡</div><p>暂无设备</p></div>';
        return;
    }

    container.innerHTML = devices.slice(0, 6).map(device => renderDeviceCard(device, true)).join('');
}

function renderDeviceCard(device, compact = false) {
    const state = JSON.parse(device.state || '{}');
    const icon = typeIcons[device.type_code] || typeIcons.default;
    const isOnline = device.is_online;

    let controls = '';

    if (device.type_code === 'light' || device.type_code === 'switch' || device.type_code === 'outlet') {
        const btnClass = state.on ? 'active' : '';
        const btnText = state.on ? 'ON' : 'OFF';
        controls = '<div class="device-controls">' +
            '<button class="device-btn ' + btnClass + '" onclick="controlDevice(' + device.id + ', \x27toggle\x27)">' + btnText + '</button>' +
            '</div>';

        if (device.type_code === 'light' && !compact) {
            const brightness = state.brightness || 100;
            controls += '<input type="range" class="device-slider" min="0" max="100" value="' + brightness + '"' +
                ' oninput="controlDeviceWithParam(' + device.id + ', \x27set_brightness\x27, \x27brightness\x27, this.value)">';
        }
    } else if (device.type_code === 'fan') {
        const btnClass = state.on ? 'active' : '';
        const btnText = state.on ? 'ON' : 'OFF';
        controls = '<div class="device-controls">' +
            '<button class="device-btn ' + btnClass + '" onclick="controlDevice(' + device.id + ', \x27toggle\x27)">' + btnText + '</button>' +
            '<button class="device-btn" onclick="controlDeviceWithParam(' + device.id + ', \x27set_speed\x27, \x27speed\x27, 1)">1档</button>' +
            '<button class="device-btn" onclick="controlDeviceWithParam(' + device.id + ', \x27set_speed\x27, \x27speed\x27, 3)">3档</button>' +
            '<button class="device-btn" onclick="controlDeviceWithParam(' + device.id + ', \x27set_speed\x27, \x27speed\x27, 5)">5档</button>' +
            '</div>';
    } else if (device.type_code === 'curtain') {
        controls = '<div class="device-controls">' +
            '<button class="device-btn" onclick="controlDevice(' + device.id + ', \x27open\x27)">打开</button>' +
            '<button class="device-btn" onclick="controlDevice(' + device.id + ', \x27close\x27)">关闭</button>' +
            '</div>';
    } else if (device.type_code === 'lock') {
        const btnClass = state.lockstate === 'locked' ? 'active' : '';
        const btnText = state.lockstate === 'locked' ? '已锁' : '已解锁';
        controls = '<div class="device-controls">' +
            '<button class="device-btn ' + btnClass + '" onclick="controlDevice(' + device.id + ', \x27toggle\x27)">' + btnText + '</button>' +
            '</div>';
    } else if (device.type_code === 'thermostat') {
        controls = '<div class="device-state">温度: ' + state.temperature + '°C / 设定: ' + state.setpoint + '°C</div>';
    } else if (device.type_code.startsWith('sensor_')) {
        let stateText = '';
        if (device.type_code === 'sensor_motion') {
            stateText = state.occupancy ? '有人' : '无人';
        } else if (device.type_code === 'sensor_door') {
            stateText = state.contact ? '关闭' : '打开';
        } else {
            stateText = state.temperature + '°C / ' + state.humidity + '%';
        }
        controls = '<div class="device-state">' + stateText + '</div>';
    }

    const cardClass = isOnline ? '' : 'offline';
    const statusClass = isOnline ? '' : 'offline';
    const roomName = device.room_name || device.type_name || '';

    return '<div class="device-card ' + cardClass + '" data-device-id="' + device.id + '">' +
        '<div class="device-header">' +
        '<span class="device-icon">' + icon + '</span>' +
        '<span class="device-status ' + statusClass + '"></span>' +
        '</div>' +
        '<div class="device-name">' + device.name + '</div>' +
        '<div class="device-room">' + roomName + '</div>' +
        controls +
        '</div>';
}

async function loadDevices() {
    try {
        const result = await api('/devices');
        if (result.code === 0) {
            state.devices = result.data;
            renderDevices(state.devices);
        }

        loadFamilyFilters('device-family-filter', 'device-room-filter');
        loadDeviceTypeFilter();
    } catch (error) {
        console.error('加载设备失败:', error);
    }
}

function renderDevices(devices) {
    const container = document.getElementById('device-list');
    document.getElementById('device-count').textContent = devices.length + ' 个设备';

    if (devices.length === 0) {
        container.innerHTML = '<div class="empty-state" style="grid-column: 1/-1"><div class="icon">💡</div><p>暂无设备</p><button class="btn btn-primary" onclick="discoverDevices()">发现设备</button></div>';
        return;
    }

    container.innerHTML = devices.map(device => renderDeviceCard(device)).join('');
}

async function loadFamilyFilters(familySelectId, roomSelectId) {
    try {
        const result = await api('/families');
        if (result.code === 0) {
            state.families = result.data;
            const select = document.getElementById(familySelectId);
            select.innerHTML = '<option value="">全部家庭</option>' +
                state.families.map(f => `<option value="${f.id}">${f.name}</option>`).join('');

            select.onchange = () => {
                state.currentFamily = select.value;
                filterDevices();
            };
        }
    } catch (error) {
        console.error('加载家庭失败:', error);
    }

    try {
        const result = await api('/rooms');
        if (result.code === 0) {
            state.rooms = result.data;
            const select = document.getElementById(roomSelectId);
            select.innerHTML = '<option value="">全部房间</option>' +
                state.rooms.map(r => `<option value="${r.id}">${r.name}</option>`).join('');

            select.onchange = filterDevices;
        }
    } catch (error) {
        console.error('加载房间失败:', error);
    }
}

async function loadDeviceTypeFilter() {
    try {
        const result = await api('/devices/types');
        if (result.code === 0) {
            const select = document.getElementById('device-type-filter');
            select.innerHTML = '<option value="">全部类型</option>' +
                result.data.map(t => `<option value="${t.code}">${t.name}</option>`).join('');

            select.onchange = filterDevices;
        }
    } catch (error) {
        console.error('加载设备类型失败:', error);
    }
}

function filterDevices() {
    const familyId = document.getElementById('device-family-filter')?.value || '';
    const roomId = document.getElementById('device-room-filter')?.value || '';
    const typeCode = document.getElementById('device-type-filter')?.value || '';

    let filtered = [...state.devices];

    if (familyId) filtered = filtered.filter(d => d.family_id == familyId);
    if (roomId) filtered = filtered.filter(d => d.room_id == roomId);
    if (typeCode) filtered = filtered.filter(d => d.type_code === typeCode);

    renderDevices(filtered);
}

async function controlDevice(deviceId, action, params = {}) {
    try {
        const result = await api(`/devices/${deviceId}/control`, {
            method: 'POST',
            body: JSON.stringify({ action, params })
        });

        if (result.code === 0) {
            showToast('控制指令已发送', 'success');
            loadDevices();
        } else {
            showToast(result.message || '控制失败', 'error');
        }
    } catch (error) {
        showToast('控制失败', 'error');
    }
}

async function controlDeviceWithParam(deviceId, action, key, value) {
    const params = {};
    params[key] = isNaN(Number(value)) ? value : Number(value);
    await controlDevice(deviceId, action, params);
}

async function discoverDevices() {
    showToast('正在发现设备...', 'info');
    try {
        const result = await api('/devices/discover', {
            method: 'POST',
            body: JSON.stringify({})
        });

        if (result.code === 0) {
            const devices = result.data.devices || [];
            showToast(`发现 ${devices.length} 个设备`, 'success');

            if (devices.length > 0) {
                showDiscoveredDevices(devices);
            }
        }
    } catch (error) {
        showToast('设备发现失败', 'error');
    }
}

function showDiscoveredDevices(devices) {
    const body = `
        <div class="form-row">
            <p class="text-muted">发现以下设备，请选择要添加的设备：</p>
        </div>
        ${devices.map(device => `
            <div class="form-row">
            <label class="form-check">
                <input type="checkbox" name="discover-device" value="${device.matter_unique_id}" data-name="${device.name}">
                ${device.name} (${typeNames[device.type] || device.type})
            </label>
        `).join('')}
    `;

    showModal('发现设备', body, [
        { text: '取消', class: 'btn-secondary', action: closeModal },
        { text: '添加选中', class: 'btn-primary', action: () => commissionDevices(devices) }
    ]);
}

async function commissionDevices(devices) {
    const checkboxes = document.querySelectorAll('input[name="discover-device"]:checked');

    for (const checkbox of checkboxes) {
        const uid = checkbox.value;
        const device = devices.find(d => d.matter_unique_id === uid);
        await commissionDevice(uid, device);
    }

    closeModal();
    loadDevices();
}

async function commissionDevice(uid, device) {
    try {
        const families = await api('/families');
        const familyId = families.data?.[0]?.id || 1;

        const types = await api('/devices/types');
        const type = types.data?.find(t => t.code === device.type);

        await api('/devices/commission', {
            method: 'POST',
            body: JSON.stringify({
                family_id: familyId,
                matter_unique_id: uid,
                name: device.name,
                type_id: type?.id || 1
            })
        });
    } catch (error) {
        console.error('设备入网失败:', error);
    }
}

function showDeviceModal() {
    const body = `
        <div class="form-row">
            <label>设备名称</label>
            <input type="text" id="device-name" placeholder="请输入设备名称">
        </div>
        <div class="form-row">
            <label>设备类型</label>
            <select id="device-type">
                <option value="light">智能灯</option>
                <option value="switch">智能开关</option>
                <option value="outlet">智能插座</option>
                <option value="fan">智能风扇</option>
                <option value="curtain">智能窗帘</option>
                <option value="lock">智能门锁</option>
            </select>
        </div>
        <div class="form-row">
            <label>所属家庭</label>
            <select id="device-family">
                ${state.families.map(f => `<option value="${f.id}">${f.name}</option>`).join('')}
            </select>
        </div>
    `;

    showModal('添加设备', body, [
        { text: '取消', class: 'btn-secondary', action: closeModal },
        { text: '添加', class: 'btn-primary', action: createDevice }
    ]);
}

async function createDevice() {
    const name = document.getElementById('device-name').value;
    const typeCode = document.getElementById('device-type').value;
    const familyId = document.getElementById('device-family').value;

    if (!name) {
        showToast('请输入设备名称', 'warning');
        return;
    }

    try {
        const types = await api('/devices/types');
        const type = types.data?.find(t => t.code === typeCode);

        const result = await api('/devices', {
            method: 'POST',
            body: JSON.stringify({
                family_id: familyId,
                type_id: type?.id || 1,
                name
            })
        });

        if (result.code === 0) {
            showToast('设备添加成功', 'success');
            closeModal();
            loadDevices();
        } else {
            showToast(result.message || '添加失败', 'error');
        }
    } catch (error) {
        showToast('添加失败', 'error');
    }
}

async function loadScenes() {
    try {
        const result = await api('/scenes');
        if (result.code === 0) {
            state.scenes = result.data;
            renderScenes(state.scenes);
        }

        const families = await api('/families');
        if (families.code === 0) {
            state.families = families.data;
            const select = document.getElementById('scene-family-filter');
            select.innerHTML = '<option value="">全部家庭</option>' +
                state.families.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
            select.onchange = async () => {
                const filtered = select.value
                    ? state.scenes.filter(s => s.family_id == select.value)
                    : state.scenes;
                renderScenes(filtered);
            };
        }
    } catch (error) {
        console.error('加载场景失败:', error);
    }
}

function renderScenes(scenes) {
    const container = document.getElementById('scene-list');

    if (scenes.length === 0) {
        container.innerHTML = '<div class="empty-state" style="grid-column: 1/-1"><div class="icon">🎬</div><p>暂无场景</p></div>';
        return;
    }

    container.innerHTML = scenes.map(scene => `
        <div class="scene-card" style="border-left: 4px solid ${scene.color}">
            ${scene.is_favorite ? '<span class="favorite-badge">⭐</span>' : ''}
            <div class="scene-icon">${sceneIcons[scene.icon] || '🎭'}</div>
            <div class="scene-name">${scene.name}</div>
            <div class="scene-desc">${scene.description || ''}</div>
            <div class="scene-actions">
                <button class="scene-action-btn" onclick="executeScene(${scene.id})">执行</button>
            </div>
        </div>
    `).join('');
}

async function executeScene(sceneId) {
    try {
        const result = await api(`/scenes/${sceneId}/execute`, {
            method: 'POST'
        });

        if (result.code === 0) {
            showToast('场景执行成功', 'success');
            loadDevices();
        } else {
            showToast(result.message || '执行失败', 'error');
        }
    } catch (error) {
        showToast('执行失败', 'error');
    }
}

function showSceneModal() {
    const body = `
        <div class="form-row">
            <label>场景名称</label>
            <input type="text" id="scene-name" placeholder="如：回家模式">
        </div>
        <div class="form-row">
            <label>图标</label>
            <select id="scene-icon">
                <option value="home">🏠 回家</option>
                <option value="walk">🚶 离家</option>
                <option value="film">🎬 观影</option>
                <option value="moon">🌙 睡眠</option>
                <option value="sun">☀️ 起床</option>
                <option value="party">🎉 派对</option>
            </select>
        </div>
        <div class="form-row">
            <label>颜色</label>
            <input type="color" id="scene-color" value="#4A90D9">
        </div>
        <div class="form-row">
            <label>描述</label>
            <textarea id="scene-desc" rows="2" placeholder="场景描述"></textarea>
        </div>
        <div class="form-row">
            <label>所属家庭</label>
            <select id="scene-family">
                ${state.families.map(f => `<option value="${f.id}">${f.name}</option>`).join('')}
            </select>
        </div>
    `;

    showModal('创建场景', body, [
        { text: '取消', class: 'btn-secondary', action: closeModal },
        { text: '创建', class: 'btn-primary', action: createScene }
    ]);
}

async function createScene() {
    const name = document.getElementById('scene-name').value;
    const icon = document.getElementById('scene-icon').value;
    const color = document.getElementById('scene-color').value;
    const description = document.getElementById('scene-desc').value;
    const familyId = document.getElementById('scene-family').value;

    if (!name) {
        showToast('请输入场景名称', 'warning');
        return;
    }

    try {
        const result = await api('/scenes', {
            method: 'POST',
            body: JSON.stringify({ name, icon, color, description, family_id: familyId })
        });

        if (result.code === 0) {
            showToast('场景创建成功', 'success');
            closeModal();
            loadScenes();
        } else {
            showToast(result.message || '创建失败', 'error');
        }
    } catch (error) {
        showToast('创建失败', 'error');
    }
}

async function loadAutomations() {
    try {
        const result = await api('/automations');
        if (result.code === 0) {
            state.automations = result.data;
            renderAutomations(state.automations);
        }

        const families = await api('/families');
        if (families.code === 0) {
            state.families = families.data;
            const select = document.getElementById('automation-family-filter');
            select.innerHTML = '<option value="">全部家庭</option>' +
                state.families.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
        }
    } catch (error) {
        console.error('加载自动化规则失败:', error);
    }
}

function renderAutomations(automations) {
    const container = document.getElementById('automation-list');

    if (automations.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="icon">⚡</div><p>暂无自动化规则</p></div>';
        return;
    }

    container.innerHTML = automations.map(rule => {
        const triggerConfig = JSON.parse(rule.trigger_config || '{}');
        const triggerText = getTriggerText(rule.trigger_type, triggerConfig);

        return `
            <div class="automation-card">
                <div class="automation-header">
                    <span class="automation-name">${rule.name}</span>
                    <div class="automation-toggle ${rule.is_enabled ? 'enabled' : ''}" 
                         onclick="toggleAutomation(${rule.id}, ${!rule.is_enabled})"></div>
                </div>
                <div class="automation-desc">${rule.description || ''}</div>
                <div class="automation-meta">
                    <span>触发: ${triggerText}</span>
                    <span>触发次数: ${rule.trigger_count || 0}</span>
                    ${rule.last_triggered_at ? `<span>上次: ${formatTime(rule.last_triggered_at)}</span>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function getTriggerText(type, config) {
    switch (type) {
        case 'time':
            return `定时 ${config.time || ''}`;
        case 'schedule':
            return '定时触发';
        case 'device_state':
            return '设备状态触发';
        case 'sensor':
            return '传感器触发';
        case 'manual':
            return '手动触发';
        default:
            return type;
    }
}

async function toggleAutomation(ruleId, enabled) {
    try {
        await api(`/automations/${ruleId}/toggle`, {
            method: 'POST',
            body: JSON.stringify({ enabled })
        });
        loadAutomations();
    } catch (error) {
        showToast('操作失败', 'error');
    }
}

function showAutomationModal() {
    const body = `
        <div class="form-row">
            <label>规则名称</label>
            <input type="text" id="auto-name" placeholder="如：人体感应开灯">
        </div>
        <div class="form-row">
            <label>触发类型</label>
            <select id="auto-trigger" onchange="updateAutomationTriggerChange()">
                <option value="time">定时触发</option>
                <option value="device_state">设备状态</option>
                <option value="sensor">传感器触发</option>
            </select>
        </div>
        <div class="form-row" id="auto-time-row" style="display:none">
            <label>触发时间</label>
            <input type="time" id="auto-time" value="18:00">
        </div>
        <div class="form-row">
            <label>描述</label>
            <textarea id="auto-desc" rows="2" placeholder="规则描述"></textarea>
        </div>
        <div class="form-row">
            <label>所属家庭</label>
            <select id="auto-family">
                ${state.families.map(f => `<option value="${f.id}">${f.name}</option>`).join('')}
            </select>
        </div>
    `;

    showModal('创建自动化规则', body, [
        { text: '取消', class: 'btn-secondary', action: closeModal },
        { text: '创建', class: 'btn-primary', action: createAutomation }
    ]);
}

function updateAutomationTriggerChange() {
    const trigger = document.getElementById('auto-trigger').value;
    document.getElementById('auto-time-row').style.display = trigger === 'time' ? 'block' : 'none';
}

async function createAutomation() {
    const name = document.getElementById('auto-name').value;
    const triggerType = document.getElementById('auto-trigger').value;
    const description = document.getElementById('auto-desc').value;
    const familyId = document.getElementById('auto-family').value;

    if (!name) {
        showToast('请输入规则名称', 'warning');
        return;
    }

    let triggerConfig = {};
    if (triggerType === 'time') {
        triggerConfig.time = document.getElementById('auto-time').value;
    }

    try {
        const result = await api('/automations', {
            method: 'POST',
            body: JSON.stringify({
                name, trigger_type: triggerType, trigger_config: triggerConfig, description, family_id: familyId,
                conditions: [], actions: []
            })
        });

        if (result.code === 0) {
            showToast('规则创建成功', 'success');
            closeModal();
            loadAutomations();
        } else {
            showToast(result.message || '创建失败', 'error');
        }
    } catch (error) {
        showToast('创建失败', 'error');
    }
}

async function loadLogs() {
    try {
        const result = await api('/logs');
        if (result.code === 0) {
            state.logs = result.data;
            renderLogs(state.logs);
        }
    } catch (error) {
        console.error('加载日志失败:', error);
    }
}

function renderLogs(logs) {
    const tbody = document.getElementById('log-tbody');

    if (logs.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="empty-state">暂无日志</td></tr>';
        return;
    }

    tbody.innerHTML = logs.map(log => `
        <tr>
            <td>${formatFullTime(log.created_at)}</td>
            <td><span class="log-level ${log.level}">${log.level.toUpperCase()}</span></td>
            <td>${log.category}</td>
            <td>${log.message}</td>
        </tr>
    `).join('');
}

async function loadAlerts() {
    try {
        const result = await api('/alerts');
        if (result.code === 0) {
            state.alerts = result.data;
            renderAlerts(state.alerts);
        }
    } catch (error) {
        console.error('加载告警失败:', error);
    }
}

function renderAlerts(alerts) {
    const container = document.getElementById('alert-list');

    if (alerts.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="icon">✅</div><p>暂无告警</p></div>';
        return;
    }

    container.innerHTML = alerts.map(alert => `
        <div class="alert-card ${alert.severity} ${alert.is_read ? 'read' : ''}">
            <div class="alert-header">
                <span class="alert-type">${alert.type}</span>
                <span class="alert-time">${formatTime(alert.created_at)}</span>
            </div>
            <div class="alert-title">${alert.title}</div>
            <div class="alert-message">${alert.message || ''}</div>
            <div class="alert-actions">
                ${!alert.is_read ? `<button class="btn btn-sm btn-secondary" onclick="markAlertRead(${alert.id})">标记已读</button>` : ''}
                ${!alert.is_resolved ? `<button class="btn btn-sm btn-primary" onclick="resolveAlert(${alert.id})">解决</button>` : ''}
            </div>
        </div>
    `).join('');
}

async function markAlertRead(alertId) {
    try {
        await api(`/alerts/${alertId}/read`, { method: 'PUT' });
        loadAlerts();
    } catch (error) {
        showToast('操作失败', 'error');
    }
}

async function resolveAlert(alertId) {
    try {
        await api(`/alerts/${alertId}/resolve`, { method: 'PUT' });
        loadAlerts();
    } catch (error) {
        showToast('操作失败', 'error');
    }
}

async function markAllAlertsRead() {
    try {
        await api('/alerts/read-all', { method: 'PUT' });
        loadAlerts();
        showToast('已全部标记为已读', 'success');
    } catch (error) {
        showToast('操作失败', 'error');
    }
}

async function loadSettings() {
    try {
        const familiesResult = await api('/families');
        if (familiesResult.code === 0) {
            state.families = familiesResult.data;
            renderFamilies(state.families);
        }

        const roomsResult = await api('/rooms');
        if (roomsResult.code === 0) {
            state.rooms = roomsResult.data;
            renderRooms(state.rooms);
        }

        const statusResult = await api('/system/status');
        if (statusResult.code === 0) {
            renderSystemInfo(statusResult.data);
        }
    } catch (error) {
        console.error('加载设置失败:', error);
    }
}

function renderFamilies(families) {
    const container = document.getElementById('family-list');

    if (families.length === 0) {
        container.innerHTML = '<div class="empty-state">暂无家庭</div>';
        return;
    }

    container.innerHTML = families.map(family => `
        <div class="family-card">
            <div class="family-name">${family.name}</div>
            <div class="text-muted" style="font-size: 0.8rem">${family.description || ''}</div>
        </div>
    `).join('');
}

function renderRooms(rooms) {
    const container = document.getElementById('room-list');

    if (rooms.length === 0) {
        container.innerHTML = '<div class="empty-state">暂无房间</div>';
        return;
    }

    container.innerHTML = rooms.map(room => `
        <div class="room-card">
            <div class="room-name">${room.name}</div>
            <div class="text-muted" style="font-size: 0.8rem">设备: ${room.device_count || 0} 个设备</div>
        </div>
    `).join('');
}

function renderSystemInfo(info) {
    const container = document.getElementById('system-info');
    container.innerHTML = `
        <div class="system-info-item">
            <span class="info-label">应用名称</span>
            <span class="info-value">${info.app || 'SmartHome Hub'}</span>
        </div>
        <div class="system-info-item">
            <span class="info-label">版本</span>
            <span class="info-value">${info.version || '1.0.0'}</span>
        </div>
        <div class="system-info-item">
            <span class="info-label">数据库状态</span>
            <span class="info-value">${info.database || 'connected'}</span>
        </div>
        <div class="system-info-item">
            <span class="info-label">Matter桥接</span>
            <span class="info-value">${info.matter_bridge?.status || 'unknown'}</span>
        </div>
    `;
}

function showFamilyModal() {
    const body = `
        <div class="form-row">
            <label>家庭名称</label>
            <input type="text" id="family-name" placeholder="请输入家庭名称">
        </div>
        <div class="form-row">
            <label>描述</label>
            <input type="text" id="family-desc" placeholder="家庭描述">
        </div>
    `;

    showModal('创建家庭', body, [
        { text: '取消', class: 'btn-secondary', action: closeModal },
        { text: '创建', class: 'btn-primary', action: createFamily }
    ]);
}

async function createFamily() {
    const name = document.getElementById('family-name').value;
    const description = document.getElementById('family-desc').value;

    if (!name) {
        showToast('请输入家庭名称', 'warning');
        return;
    }

    try {
        const result = await api('/families', {
            method: 'POST',
            body: JSON.stringify({ name, description })
        });

        if (result.code === 0) {
            showToast('家庭创建成功', 'success');
            closeModal();
            loadSettings();
        } else {
            showToast(result.message || '创建失败', 'error');
        }
    } catch (error) {
        showToast('创建失败', 'error');
    }
}

function showRoomModal() {
    const body = `
        <div class="form-row">
            <label>房间名称</label>
            <input type="text" id="room-name" placeholder="请输入房间名称">
        </div>
        <div class="form-row">
            <label>房间类型</label>
            <select id="room-type">
                <option value="living_room">客厅</option>
                <option value="bedroom">卧室</option>
                <option value="kitchen">厨房</option>
                <option value="bathroom">卫生间</option>
                <option value="study">书房</option>
                <option value="balcony">阳台</option>
            </select>
        </div>
        <div class="form-row">
            <label>所属家庭</label>
            <select id="room-family">
                ${state.families.map(f => `<option value="${f.id}">${f.name}</option>`).join('')}
            </select>
        </div>
    `;

    showModal('创建房间', body, [
        { text: '取消', class: 'btn-secondary', action: closeModal },
        { text: '创建', class: 'btn-primary', action: createRoom }
    ]);
}

async function createRoom() {
    const name = document.getElementById('room-name').value;
    const type = document.getElementById('room-type').value;
    const familyId = document.getElementById('room-family').value;

    if (!name) {
        showToast('请输入房间名称', 'warning');
        return;
    }

    try {
        const result = await api('/rooms', {
            method: 'POST',
            body: JSON.stringify({ name, type, family_id: familyId })
        });

        if (result.code === 0) {
            showToast('房间创建成功', 'success');
            closeModal();
            loadSettings();
        } else {
            showToast(result.message || '创建失败', 'error');
        }
    } catch (error) {
        showToast('创建失败', 'error');
    }
}

function showModal(title, body, buttons) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = body;

    const footer = document.getElementById('modal-footer');
    footer.innerHTML = '';

    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.className = `btn ${btn.class} `;
        button.textContent = btn.text;
        button.onclick = btn.action;
        footer.appendChild(button);
    });

    document.getElementById('modal-container').classList.add('active');
}

function closeModal() {
    document.getElementById('modal-container').classList.remove('active');
}

function initWebSocket() {
    const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${location.hostname}:8081`;

    try {
        const ws = new WebSocket(wsUrl);

        ws.onopen = () => {
            state.wsConnected = true;
            document.querySelector('.status-dot').classList.add('connected');
            document.querySelector('.status-indicator span:last-child').textContent = '已连接';
        };

        ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                handleWebSocketMessage(data);
            } catch (error) {
                console.error('WebSocket消息解析失败:', error);
            }
        };

        ws.onclose = () => {
            state.wsConnected = false;
            document.querySelector('.status-dot').classList.remove('connected');
            document.querySelector('.status-dot').classList.add('disconnected');
            document.querySelector('.status-indicator span:last-child').textContent = '已断开';
        };

        ws.onerror = (error) => {
            console.error('WebSocket错误:', error);
        };
    } catch (error) {
        console.error('WebSocket连接失败:', error);
    }
}

function handleWebSocketMessage(data) {
    switch (data.type) {
        case 'device_state_change':
        case 'device_state_report':
            if (state.currentPage === 'devices' || state.currentPage === 'dashboard') {
                loadDevices();
            }
            break;

        case 'scene_executed':
            showToast(`场景已执行: ${data.scene_name}`, 'success');
            break;

        case 'automation_triggered':
            showToast(`自动化触发: ${data.rule_name}`, 'info');
            if (state.currentPage === 'automations') {
                loadAutomations();
            }
            break;

        case 'alert':
        case 'new_alert':
            showToast(`新告警: ${data.title}`, 'warning');
            if (state.currentPage === 'alerts') {
                loadAlerts();
            }
            break;

        case 'notification':
            showToast(data.message || '新通知', 'info');
            break;

        case 'sensor_trigger':
            console.log('传感器触发:', data);
            break;
    }
}

function init() {
    const savedToken = localStorage.getItem('token');
    const savedUser = localStorage.getItem('user');

    if (savedToken && savedUser) {
        state.token = savedToken;
        state.user = JSON.parse(savedUser);
        showMainPage();
        initWebSocket();
        loadDashboard();
    } else {
        showLoginPage();
    }

    document.getElementById('login-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const username = document.getElementById('login-username').value;
        const password = document.getElementById('login-password').value;
        login(username, password);
    });

    document.getElementById('register-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const username = document.getElementById('reg-username').value;
        const email = document.getElementById('reg-email').value;
        const password = document.getElementById('reg-password').value;
        register(username, email, password);
    });

    document.getElementById('show-register').addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('login-form').classList.add('hidden');
        document.getElementById('register-form').classList.remove('hidden');
    });

    document.getElementById('show-login').addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('register-form').classList.add('hidden');
        document.getElementById('login-form').classList.remove('hidden');
    });

    document.getElementById('logout-btn').addEventListener('click', (e) => {
        e.preventDefault();
        logout();
    });

    document.querySelectorAll('.nav-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            showPage(item.dataset.page);
        });
    });

    document.getElementById('refresh-btn').addEventListener('click', () => {
        loadPageContent(state.currentPage);
        showToast('已刷新', 'success');
    });

    document.getElementById('modal-container').addEventListener('click', (e) => {
        if (e.target === document.getElementById('modal-container')) {
            closeModal();
        }
    });
}

document.addEventListener('DOMContentLoaded', init);
