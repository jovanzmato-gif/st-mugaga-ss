// Runs when the admin presses "Log in"
document.getElementById('login-form').addEventListener('submit', async e => {
  // Stop the browser from reloading the page. We send the login ourselves.
  e.preventDefault();

  const msg = document.getElementById('login-message');

  // Read the username and password from the form.
  // FormData reads each field using its name="..." attribute.
  const fd = new FormData(e.target);

  try {
    // Send the username and password to the login route on the back end.
    // We send JSON here, not form data, because the server expects JSON for login.
    const res = await fetch('/st-mugaga-ss/public/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        username: fd.get('username'),
        password: fd.get('password')
      })
    });

    // Read the server's reply. It will contain either a token or an error message.
    const data = await res.json();

    // If the server rejected the login, throw an error so the catch block shows it
    if (!res.ok) throw new Error(data.message);

    // Save the token in the browser so the dashboard can prove we're logged in.
    // localStorage keeps the token even after the page is closed and reopened.
    localStorage.setItem('adminToken', data.token);

    // Move to the dashboard
    location.href = 'dashboard.html';

  } catch (err) {
    // Show the error message, such as "Invalid username or password"
    msg.innerHTML = `<div class="alert error">${err.message}</div>`;
  }
});