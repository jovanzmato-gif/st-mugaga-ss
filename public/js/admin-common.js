// Stops anyone without a login token from viewing the admin pages.
// Call this at the top of every admin page.
function requireLogin() {
  // localStorage holds the token that login.js saved.
  // If there's no token, send the user back to the login page.
  if (!localStorage.getItem('adminToken')) {
    location.href = 'login.html';
  }
}

// Clears the token and returns to the login page
function logout() {
  localStorage.removeItem('adminToken');
  location.href = 'login.html';
}

// Calls the protected admin API. It attaches the login token to every request.
// Usage examples:
//   adminApi('/posts')                                   -> GET a list
//   adminApi('/posts', { method: 'POST', body: {...} })  -> send JSON
//   adminApi('/posts', { method: 'POST', body: formData }) -> send a form with a photo
async function adminApi(path, options = {}) {

  // Every admin request must carry the token in the Authorization header.
  // The server checks this header before running any admin code.
  const headers = {
    Authorization: `Bearer ${localStorage.getItem('adminToken')}`
  };

  let body = options.body;

  // If the body is a plain object (not FormData), convert it to JSON text
  // and tell the server it's JSON.
  // FormData is left alone, because the browser handles its headers itself.
  if (body && !(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(body);
  }

  // Send the request to the admin part of the API
  const res = await fetch('/st-mugaga-ss/public/api/admin' + path, {
    method: options.method || 'GET',   // GET is the default if none is given
    headers,
    body
  });

  // Read the reply. If it isn't valid JSON, use an empty object instead.
  const data = await res.json().catch(() => ({}));

  // 401 means the token is missing, invalid, or expired.
  // Clear it and send the user back to log in.
  if (res.status === 401) {
    localStorage.removeItem('adminToken');
    location.href = 'login.html';
    throw new Error('Session expired');
  }

  // Any other error: pass the server's message up to the caller
  if (!res.ok) throw new Error(data.message || 'Request failed');

  return data;
}