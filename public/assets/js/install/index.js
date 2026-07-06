/**
 * 安装向导资源加载控制器。
 *
 * 规则（按用户要求）：
 * - 不使用基于时间的兜底逻辑，也不设置 setTimeout 最大等待时间。
 * - 等待所有相关资源真正返回结果（成功或失败）后，再显示页面。
 * - 即使资源返回 404/500，也会触发 onerror，因此同样视为“请求已完成”。
 * - 不用超时逻辑跳过仍在等待响应的资源。
 */
(function () {
    var loadingScreen = document.getElementById('loadingScreen');
    var desktopBgUrl = '/assets/img/backgrounds/install-desktop.jpeg';
    var selectedBgUrl = desktopBgUrl;
    var revealed = false;

    // 移动端随机选择一张背景图。
    if (window.innerWidth <= 768) {
        var mobileBgs = [
            { cls: 'mobile-bg1', url: '/assets/img/backgrounds/install-mobile-1.png' },
            { cls: 'mobile-bg2', url: '/assets/img/backgrounds/install-mobile-2.jpg' }
        ];
        var picked = mobileBgs[Math.floor(Math.random() * mobileBgs.length)];
        document.body.classList.add(picked.cls);
        selectedBgUrl = picked.url;
    }

    // 写入已选择的背景图，让 CSS 尽早开始下载。
    document.body.style.setProperty('--install-bg-image', 'url("' + selectedBgUrl + '")');

    // 先等待 window.load（覆盖图片、样式和脚本），
    // 再显式等待背景图完成响应，最后显示页面。
    waitForWindowLoad()
        .then(function () {
            return waitAllImages([desktopBgUrl, selectedBgUrl]);
        })
        .then(revealPage)
        .catch(revealPage); // 防御性处理；内部等待逻辑正常不会 reject。

    function revealPage() {
        if (revealed) {
            return;
        }
        revealed = true;

        document.body.classList.add('resources-ready');

        if (!loadingScreen) {
            return;
        }

        loadingScreen.classList.add('fade-out');
        // 等待 CSS 透明度过渡结束后移除加载层节点。
        loadingScreen.addEventListener('transitionend', function onEnd() {
            loadingScreen.removeEventListener('transitionend', onEnd);
            if (loadingScreen && loadingScreen.parentNode) {
                loadingScreen.parentNode.removeChild(loadingScreen);
            }
        });
    }

    /**
     * 在 window.load 触发后完成等待。
     * 如果绑定监听前文档已经加载完成，则立即完成。
     * 不设置超时：只要浏览器还在加载，就继续等待。
     */
    function waitForWindowLoad() {
        return new Promise(function (resolve) {
            if (document.readyState === 'complete') {
                resolve();
                return;
            }
            window.addEventListener('load', function () {
                resolve();
            }, { once: true });
        });
    }

    /**
     * 等待所有 URL 都触发 load 或 error 后完成。
     * 不设置超时。HTTP 错误也会触发 onerror，
     * 这里将其视为“网络请求已经返回结果”。
     */
    function waitAllImages(urls) {
        var unique = uniq(urls);
        var jobs = unique.map(waitOneImage);
        return Promise.all(jobs);
    }

    function waitOneImage(url) {
        return new Promise(function (resolve) {
            if (!url) {
                resolve();
                return;
            }

            var img = new Image();
            var done = false;

            function finish() {
                if (done) return;
                done = true;
                img.onload = null;
                img.onerror = null;
                resolve();
            }

            img.onload = finish;
            img.onerror = finish;
            img.src = url;

            // 如果浏览器已有缓存且 complete 为 true，
            // onload 可能在绑定前已经触发，此时直接完成。
            if (img.complete) {
                finish();
            }
        });
    }

    function uniq(arr) {
        var out = [];
        for (var i = 0; i < arr.length; i++) {
            var v = arr[i];
            if (v && out.indexOf(v) === -1) {
                out.push(v);
            }
        }
        return out;
    }
})();

// ---------- 表单辅助函数（行为保持不变） ----------

function showClientError(message) {
    var errorBox = document.getElementById('clientError');
    var errorText = document.getElementById('clientErrorText');
    if (!errorBox || !errorText) return;
    errorText.textContent = message;
    errorBox.style.display = 'flex';
}

function hideClientError() {
    var errorBox = document.getElementById('clientError');
    if (!errorBox) return;
    errorBox.style.display = 'none';
}

function validateInstallerForm() {
    var username = document.getElementById('username');
    var password = document.getElementById('password');
    var submitBtn = document.getElementById('installSubmitBtn');

    if (!username || !password) return true;

    if (submitBtn && submitBtn.disabled) {
        return false;
    }

    if (username.value.trim() === '' || password.value === '') {
        showClientError('用户名和密码不能为空');
        return false;
    }

    hideClientError();
    lockInstallSubmitButton();
    return true;
}

function lockInstallSubmitButton() {
    var submitBtn = document.getElementById('installSubmitBtn');
    if (!submitBtn) return;

    var text = submitBtn.querySelector('.btn-text');

    submitBtn.disabled = true;
    submitBtn.classList.add('is-loading');
    submitBtn.setAttribute('aria-busy', 'true');

    if (text) {
        text.textContent = '安装中...';
    }
}

window.addEventListener('pageshow', function () {
    var submitBtn = document.getElementById('installSubmitBtn');
    if (!submitBtn) return;

    var text = submitBtn.querySelector('.btn-text');

    submitBtn.disabled = false;
    submitBtn.classList.remove('is-loading');
    submitBtn.removeAttribute('aria-busy');

    if (text) {
        text.textContent = '完成安装';
    }
});

function togglePassword(inputId, btn) {
    var input = document.getElementById(inputId);
    var eyeOpen = btn.querySelector('.eye-open');
    var eyeClosed = btn.querySelector('.eye-closed');

    if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
    } else {
        input.type = 'password';
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
    }
}
