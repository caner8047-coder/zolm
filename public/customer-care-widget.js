(function () {
  'use strict';

  var script = document.currentScript;
  if (!script || !script.dataset.key) return;

  var publicKey = script.dataset.key;
  var apiBase = (script.dataset.apiBase || new URL(script.src).origin + '/api/customer-care/widget').replace(/\/$/, '');
  var endpoint = apiBase + '/' + encodeURIComponent(publicKey);
  var storageKey = 'zolm_cc_widget_' + publicKey;
  var token = null;
  var widgetConfig = null;
  var leadKey = null;
  var cursor = 0;
  var pollTimer = null;

  try {
    var saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    if (saved && saved.token && new Date(saved.expires_at).getTime() > Date.now()) token = saved.token;
    if (saved && saved.lead_key) leadKey = saved.lead_key;
  } catch (_) {}
  leadKey = leadKey || ('lead-' + (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2)));

  var host = document.createElement('div');
  host.id = 'zolm-customer-care-widget';
  document.body.appendChild(host);
  var root = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
  root.innerHTML = [
    '<style>',
    ':host{all:initial;font-family:Inter,ui-sans-serif,system-ui,-apple-system,sans-serif}',
    '.launcher{position:fixed;right:20px;bottom:20px;z-index:2147483000;border:0;border-radius:8px;background:#0f172a;color:#fff;padding:12px 16px;min-height:44px;font-weight:600;cursor:pointer;box-shadow:0 8px 24px #0f172a33}',
    '.panel{position:fixed;right:20px;bottom:76px;z-index:2147483000;width:min(380px,calc(100vw - 24px));height:min(590px,calc(100vh - 110px));display:none;flex-direction:column;border:1px solid #e2e8f0;border-radius:10px;background:#fff;box-shadow:0 18px 48px #0f172a2e;overflow:hidden}',
    '.panel.open{display:flex}.head{padding:14px 16px;border-bottom:1px solid #e2e8f0;color:#0f172a;font-weight:700;display:flex;gap:10px;align-items:center}.head img{width:34px;height:34px;border-radius:6px;object-fit:contain}.head-copy{min-width:0}.sub{display:block;color:#64748b;font-size:12px;font-weight:400;margin-top:3px}',
    '.messages{flex:1;overflow:auto;padding:14px;background:#f8fafc}.msg{max-width:82%;margin:0 0 10px;padding:9px 11px;border-radius:8px;background:#fff;border:1px solid #e2e8f0;color:#0f172a;font-size:14px;line-height:1.4;white-space:pre-wrap}.msg.me{margin-left:auto;background:#0f172a;color:#fff;border-color:#0f172a}',
    '.setup{padding:14px;overflow:auto}.setup input,.setup textarea,.composer .text-input{box-sizing:border-box;width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:10px 11px;font:inherit;font-size:16px;margin-bottom:8px}.setup textarea{min-height:70px;resize:vertical}.consent{display:flex;gap:8px;color:#475569;font-size:12px;line-height:1.4;margin:8px 0}.consent input{width:auto;margin:2px 0 0}.prompts{display:flex;flex-wrap:wrap;gap:6px;margin:0 0 10px}.prompt{border:1px solid #cbd5e1;background:#fff;color:#334155;border-radius:6px;padding:7px 9px;font-size:12px;cursor:pointer}.primary{width:100%;min-height:44px;border:0;border-radius:6px;background:#0f172a;color:#fff;font-weight:600;cursor:pointer}.composer{display:none;gap:6px;padding:12px;border-top:1px solid #e2e8f0;align-items:center}.composer.active{display:flex}.composer .text-input{margin:0;min-width:0}.composer button,.file-label{min-width:44px;min-height:44px;border:0;border-radius:6px;background:#0f172a;color:#fff;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0 10px}.file-label input{display:none}.handoff{background:#475569!important}.status{padding:6px 14px;color:#64748b;font-size:11px;background:#fff}.powered{display:none;padding:4px 14px 9px;text-align:center;color:#94a3b8;font-size:10px}.error{color:#b91c1c}',
    '@media(max-width:520px){.panel{right:0;bottom:0;width:100vw;height:100dvh;border-radius:0}.launcher{right:12px;bottom:12px}}',
    '</style>',
    '<button class="launcher" type="button" aria-expanded="false">Destek</button>',
    '<section class="panel" role="dialog" aria-label="Müşteri desteği">',
    '<header class="head"><img alt="" hidden><span class="head-copy"><span class="title">Canlı Destek</span><span class="sub">Mesajlarınıza buradan devam edebilirsiniz.</span></span></header>',
    '<div class="setup">',
    '<div class="prompts"></div>',
    '<input name="name" autocomplete="name" placeholder="Adınız (isteğe bağlı)">',
    '<input name="email" type="email" autocomplete="email" placeholder="E-posta (isteğe bağlı)">',
    '<input name="phone" autocomplete="tel" placeholder="Telefon (isteğe bağlı)">',
    '<textarea name="purpose" placeholder="Size hangi konuda yardımcı olabiliriz?"></textarea>',
    '<label class="consent"><input name="consent" type="checkbox"><span>Mesajlarımın talebimin yanıtlanması amacıyla işlenmesini ve aydınlatma metnini kabul ediyorum.</span></label>',
    '<label class="consent"><input name="marketing_consent" type="checkbox"><span class="marketing-text">Kampanya ve duyurular için ayrıca iletişim izni veriyorum (isteğe bağlı).</span></label>',
    '<button class="primary" type="button">Sohbeti Başlat</button>',
    '</div>',
    '<div class="messages" aria-live="polite"></div>',
    '<div class="status"></div>',
    '<form class="composer"><label class="file-label" title="Dosya ekle" hidden><input class="attachment" type="file" accept="image/jpeg,image/png,image/webp,application/pdf"><span>+</span></label><input class="text-input" maxlength="2000" aria-label="Mesaj" placeholder="Mesajınızı yazın…"><button class="handoff" type="button" title="Temsilci iste">İnsan</button><button type="submit">Gönder</button></form>',
    '<div class="powered">ZOLM AI destek altyapısı</div>',
    '</section>'
  ].join('');

  var launcher = root.querySelector('.launcher');
  var panel = root.querySelector('.panel');
  var setup = root.querySelector('.setup');
  var messages = root.querySelector('.messages');
  var composer = root.querySelector('.composer');
  var status = root.querySelector('.status');

  function applyConfig(config) {
    widgetConfig = config || {};
    root.querySelector('.title').textContent = widgetConfig.name || 'Canlı Destek';
    root.querySelector('.sub').textContent = widgetConfig.greeting || 'Mesajlarınıza buradan devam edebilirsiniz.';
    var color = widgetConfig.primary_color || '#0f172a';
    launcher.style.backgroundColor = color;
    root.querySelectorAll('.primary,.composer button,.file-label').forEach(function (el) { if (!el.classList.contains('handoff')) el.style.backgroundColor = color; });
    var logo = root.querySelector('.head img');
    if (widgetConfig.logo_url) { logo.src = widgetConfig.logo_url; logo.hidden = false; }
    var promptBox = root.querySelector('.prompts');
    promptBox.textContent = '';
    (widgetConfig.popular_prompts || []).forEach(function (text) {
      var button = document.createElement('button'); button.type = 'button'; button.className = 'prompt'; button.textContent = text;
      button.addEventListener('click', function () { setup.querySelector('[name=purpose]').value = text; });
      promptBox.appendChild(button);
    });
    root.querySelector('.marketing-text').textContent = widgetConfig.marketing_notice_text || root.querySelector('.marketing-text').textContent;
    root.querySelector('.file-label').hidden = !widgetConfig.attachments_enabled;
    root.querySelector('.powered').style.display = widgetConfig.powered_by_visible === false ? 'none' : 'block';
  }

  function setStatus(text, isError) {
    status.textContent = text || '';
    status.className = 'status' + (isError ? ' error' : '');
  }

  function renderMessage(message) {
    if (!message || !message.body || root.querySelector('[data-message-id="' + message.id + '"]')) return;
    var item = document.createElement('div');
    item.className = 'msg' + (message.direction === 'inbound' ? ' me' : '');
    item.dataset.messageId = message.id;
    item.textContent = message.body;
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
  }

  function activateChat() {
    setup.style.display = 'none';
    composer.classList.add('active');
    poll();
  }

  async function api(path, options) {
    options = options || {};
    options.headers = Object.assign({}, options.headers || {});
    if (!(options.body instanceof FormData)) options.headers['Content-Type'] = 'application/json';
    if (token) options.headers.Authorization = 'Bearer ' + token;
    var response = await fetch(endpoint + path, options);
    var data = await response.json().catch(function () { return {}; });
    if (!response.ok) throw new Error(data.error || 'İşlem tamamlanamadı.');
    return data;
  }

  api('/configuration', { method: 'GET' }).then(function (data) { applyConfig(data.widget); }).catch(function () {});

  async function poll() {
    if (!token || !panel.classList.contains('open')) return;
    try {
      var data = await api('/messages?after_id=' + cursor, { method: 'GET' });
      var outboundIds = [];
      (data.messages || []).forEach(function (message) {
        renderMessage(message);
        if (message.direction === 'outbound') outboundIds.push(message.id);
      });
      cursor = data.cursor || cursor;
      if (outboundIds.length) await api('/ack', { method: 'POST', body: JSON.stringify({ message_ids: outboundIds }) });
      setStatus('Bağlı');
    } catch (error) {
      setStatus(error.message, true);
      if (/token|oturum/i.test(error.message)) {
        token = null;
        localStorage.removeItem(storageKey);
        setup.style.display = '';
        composer.classList.remove('active');
      }
    } finally {
      clearTimeout(pollTimer);
      pollTimer = setTimeout(poll, 3000);
    }
  }

  launcher.addEventListener('click', function () {
    var open = panel.classList.toggle('open');
    launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && token) activateChat();
    if (!open) clearTimeout(pollTimer);
  });

  setup.querySelector('.primary').addEventListener('click', async function () {
    if (!setup.querySelector('[name=consent]').checked) {
      setStatus('Devam etmek için aydınlatma metnini kabul edin.', true);
      return;
    }
    setStatus('Güvenli oturum hazırlanıyor…');
    try {
      var data = await api('/session', {
        method: 'POST',
        body: JSON.stringify({
          consent: true,
          marketing_consent: setup.querySelector('[name=marketing_consent]').checked,
          lead: {
            name: setup.querySelector('[name=name]').value,
            email: setup.querySelector('[name=email]').value,
            phone: setup.querySelector('[name=phone]').value,
            purpose: setup.querySelector('[name=purpose]').value,
            idempotency_key: leadKey,
            contact_preference: setup.querySelector('[name=email]').value ? 'email' : (setup.querySelector('[name=phone]').value ? 'phone' : 'chat')
          }
        })
      });
      token = data.token;
      localStorage.setItem(storageKey, JSON.stringify({ token: token, expires_at: data.expires_at, lead_key: leadKey }));
      applyConfig(data.widget);
      activateChat();
    } catch (error) {
      setStatus(error.message, true);
    }
  });

  composer.addEventListener('submit', async function (event) {
    event.preventDefault();
    var input = composer.querySelector('.text-input');
    var body = input.value.trim();
    if (!body) return;
    input.disabled = true;
    try {
      var idempotency = (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2));
      var data = await api('/messages', { method: 'POST', body: JSON.stringify({ body: body, idempotency_key: idempotency, website: '' }) });
      renderMessage({ id: data.message_id, direction: 'inbound', body: body });
      input.value = '';
      setStatus('Mesaj alındı');
      poll();
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      input.disabled = false;
      input.focus();
    }
  });

  composer.querySelector('.handoff').addEventListener('click', async function () {
    try {
      var data = await api('/handoff', { method: 'POST', body: JSON.stringify({}) });
      setStatus(data.message || 'Temsilci talebiniz alındı.');
    } catch (error) { setStatus(error.message, true); }
  });

  composer.querySelector('.attachment').addEventListener('change', async function (event) {
    var file = event.target.files && event.target.files[0];
    if (!file) return;
    var form = new FormData();
    form.append('file', file);
    form.append('idempotency_key', crypto.randomUUID ? crypto.randomUUID() : 'file-' + Date.now());
    try {
      var data = await api('/attachments', { method: 'POST', body: form });
      renderMessage({ id: data.message_id, direction: 'inbound', body: 'Dosya eki gönderildi.' });
      setStatus('Dosya güvenli olarak alındı.');
    } catch (error) { setStatus(error.message, true); }
    event.target.value = '';
  });
})();
