(function () {
  'use strict';
  var endpoint = new URL('api.php', document.baseURI).href;
  var csrf = '';
  var pending = 0;
  var lastError = '';
  var originalSave = window.save;
  var originalLogout = window.logout;
  var originalDoLogin = window.doLogin;

  function request(action, method, data) {
    var headers = { 'Content-Type': 'application/json' };
    if (csrf) headers['X-CSRF-Token'] = csrf;
    return fetch(endpoint + '?action=' + encodeURIComponent(action), {
      method: method, credentials: 'same-origin', headers: headers,
      body: data === undefined ? undefined : JSON.stringify(data)
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok) throw new Error(body.error || 'تعذر الاتصال بالخادم');
        return body;
      });
    });
  }
  function showError(text) {
    lastError = text;
    if (typeof window.toast === 'function') window.toast(text, 'bad');
  }
  function setState(value) {
    if (!value || typeof value !== 'object' || !Array.isArray(value.students) || !value.settings) return false;
    DB = value;
    try { localStorage.setItem('school_app_v1', JSON.stringify(DB)); } catch (_) {}
    return true;
  }
  function saveRemote() {
    if (!csrf || !DB) return Promise.resolve();
    return request('save', 'POST', { state: DB }).catch(function (e) { showError('تعذر حفظ البيانات على الخادم: ' + e.message); });
  }
  if (typeof originalSave === 'function') {
    window.save = function () {
      originalSave();
      clearTimeout(pending);
      pending = setTimeout(saveRemote, 350);
    };
  }

  // Server-side sign-in is required; the browser's old local-only login is not used.
  window.doLogin = function (event) {
    if (event && event.preventDefault) event.preventDefault();
    var username = document.querySelector('#lu').value.trim();
    var password = document.querySelector('#lp').value;
    request('login', 'POST', { username: username, password: password }).then(function (result) {
      csrf = result.csrf || '';
      if (result.state) {
        if (!setState(result.state)) throw new Error('بيانات قاعدة البيانات غير صالحة');
      } else {
        // First login after installation: store the app's initial state on the server.
        return saveRemote();
      }
    }).then(function () {
      sessionStorage.setItem('school_auth', '1');
      var error = document.querySelector('#lerr'); if (error) error.hidden = true;
      location.hash = '#dash';
      if (typeof window.render === 'function') window.render();
    }).catch(function (e) {
      var error = document.querySelector('#lerr'); if (error) { error.hidden = false; error.textContent = e.message || 'تعذر تسجيل الدخول'; }
    });
  };

  window.logout = function () {
    var p = csrf ? request('logout', 'POST', {}) : Promise.resolve();
    p.catch(function () {}).finally(function () {
      csrf = ''; sessionStorage.removeItem('school_auth'); localStorage.removeItem('school_auth');
      location.hash = '#home'; if (typeof window.render === 'function') window.render();
    });
  };

  function addTools() {
    if (document.getElementById('dbTools')) return;
    var box = document.createElement('div'); box.id = 'dbTools';
    box.dir = 'rtl'; box.style.cssText = 'position:fixed;left:10px;bottom:12px;z-index:1000;display:flex;gap:6px;flex-wrap:wrap;background:#fff;padding:7px;border:1px solid #ccd4e0;border-radius:10px;box-shadow:0 2px 12px #0002;font:13px system-ui';
    box.innerHTML = '<button type="button" data-action="export">تنزيل نسخة بيانات</button><button type="button" data-action="import">استيراد نسخة بيانات</button><input type="file" accept="application/json,.json" hidden>';
    box.addEventListener('click', function (e) {
      var action = e.target && e.target.getAttribute('data-action');
      if (action === 'export') {
        var blob = new Blob([JSON.stringify(DB, null, 2)], { type: 'application/json' });
        var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'school-data-backup.json'; a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
      }
      if (action === 'import') box.querySelector('input').click();
    });
    box.querySelector('input').addEventListener('change', function (e) {
      var file = e.target.files && e.target.files[0]; if (!file) return;
      if (!confirm('سيتم استبدال بيانات الموقع الحالية بالنسخة المختارة. هل أنشأت نسخة احتياطية أولاً؟')) { e.target.value = ''; return; }
      var reader = new FileReader(); reader.onload = function () {
        try { var value = JSON.parse(reader.result); if (!setState(value)) throw new Error('ملف النسخة غير مناسب');
          if (typeof originalSave === 'function') originalSave(); saveRemote().then(function () { location.reload(); });
        } catch (err) { showError(err.message || 'تعذر استيراد النسخة'); }
      }; reader.readAsText(file); e.target.value = '';
    });
    document.body.appendChild(box);
  }

  // Restore server state on reload only when the server session is still valid.
  request('load', 'GET').then(function (result) {
    csrf = result.csrf || '';
    if (result.state) {
      setState(result.state); sessionStorage.setItem('school_auth', '1');
      if (typeof window.render === 'function') window.render();
    } else if (csrf) saveRemote();
    addTools();
  }).catch(function () { /* Not signed in; leave the login/home screen available. */ });
  window.addEventListener('hashchange', addTools);
  setTimeout(addTools, 500);
})();
