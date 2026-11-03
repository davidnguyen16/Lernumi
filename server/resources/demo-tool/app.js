'use strict';

(async () => {
  const status = document.getElementById('status');
  const launch = new URLSearchParams(window.location.hash.slice(1));
  const token = launch.get('lernumi_token');
  const apiVersion = launch.get('lernumi_api');
  window.history.replaceState(null, '', window.location.pathname);

  try {
    if (!token || apiVersion !== 'v1') {
      throw new Error('Open this tool from your Lernumi workspace.');
    }
    const response = await fetch('/api/tool/v1/context', {
      credentials: 'omit',
      cache: 'no-store',
      headers: { Accept: 'application/json', Authorization: 'Bearer ' + token }
    });
    const context = await response.json();
    if (!response.ok) {
      throw new Error(context.error?.message || 'The runtime context is unavailable.');
    }

    document.getElementById('workspace').textContent = context.workspace.name;
    document.getElementById('mode').textContent = context.session.mode;
    document.getElementById('read-only').textContent = String(context.session.readOnly);
    const capabilities = document.getElementById('capabilities');
    for (const capability of context.session.capabilities) {
      const item = document.createElement('li');
      item.textContent = capability;
      capabilities.append(item);
    }
    status.textContent = 'Connected to the Lernumi runtime API.';
  } catch (error) {
    status.textContent = error instanceof Error ? error.message : 'The runtime context is unavailable.';
  }
})();
