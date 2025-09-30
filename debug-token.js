// Debug script to test token authentication
// Run this in browser console when logged in

console.log('=== DEBUG TOKEN AUTHENTICATION ===');

// Check localStorage
const token = localStorage.getItem('auth_token');
console.log('Token from localStorage:', token ? `${token.substring(0, 20)}...` : 'NULL');

// Check if user is logged in
const user = localStorage.getItem('user');
console.log('User from localStorage:', user ? JSON.parse(user) : 'NULL');

// Test the API call
if (token) {
  fetch('/api/agents', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      name: 'Test User',
      email: 'test@example.com',
      password: 'password123',
      password_confirmation: 'password123',
      role: 'agent',
      enable_email_integration: false
    })
  })
  .then(response => response.json())
  .then(data => {
    console.log('API Response:', data);
  })
  .catch(error => {
    console.error('API Error:', error);
  });
} else {
  console.log('No token found - user not logged in');
}

console.log('=== END DEBUG ===');