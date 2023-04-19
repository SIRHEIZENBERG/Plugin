// Get the reCAPTCHA checkbox element
const recaptcha = document.querySelector('.g-recaptcha');

// Get the send button element
const sendBtn = document.getElementById('send-btn');

// Listen for changes to the reCAPTCHA checkbox
recaptcha.addEventListener('change', () => {
  sendBtn.disabled = (grecaptcha.getResponse().length === 0);
});

// Get the form element
const form = document.querySelector('form');

// Listen for form submission
form.addEventListener('submit', (e) => {
  e.preventDefault(); // Prevent form submission

  // Check if the reCAPTCHA checkbox is ticked
  if (grecaptcha.getResponse().length > 0) {
    // Reset the reCAPTCHA response
    grecaptcha.reset();

    // Reset the form
    form.reset();

    // Show message
    const message = document.createElement('p');
    message.textContent = 'Form submitted!';
    form.appendChild(message);
  }
});
