window.addEventListener("DOMContentLoaded", e => {

// Original JavaScript code by Chirp Internet: chirpinternet.eu
// Please acknowledge use of this code by including this header.

const showHidePassword = e => {
	let input = e.target.previousElementSibling;
	input.type = input.classList.toggle("shown") ? "text" : "password";
};

document.querySelectorAll("input[type=password]").forEach(current => {
	let showHideButton = document.createElement("div");
	showHideButton.className = "show-hide";
	showHideButton.addEventListener("click", showHidePassword);
	current.after(showHideButton);
});

});
