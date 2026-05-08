/* ==========================================================
   SIMPLE JAVASCRIPT FOR FILE MANAGER
   Functions:
   1️⃣ Toggle burger menu
   2️⃣ Toggle sort dropdown
   3️⃣ File size validation (max 1GB)
   4️⃣ Close menus when clicking outside
========================================================== */

// 1️⃣ Show or hide the burger side menu
function toggleMenu() {
  const menu = document.getElementById("menu");
  const sortBox = document.getElementById("sortDropdown");

  // Close sort dropdown if it's open
  if (sortBox) sortBox.classList.remove("active");

  // Toggle the menu visibility
  if (menu) menu.classList.toggle("active");
}

// 2️⃣ Show or hide the sort dropdown menu
function toggleSortMenu() {
  const sortBox = document.getElementById("sortDropdown");
  const menu = document.getElementById("menu");

  // Close the menu if open
  if (menu) menu.classList.remove("active");

  // Toggle dropdown visibility
  if (sortBox) sortBox.classList.toggle("active");
}

// 3️⃣ Check file size before uploading (limit: 1GB)
function checkFileSize(input) {
  const file = input.files[0]; // Get the selected file
  const maxSize = 1024 * 1024 * 1024; // 1GB in bytes

  if (!file) return; // Stop if no file selected

  if (file.size > maxSize) {
    alert("⚠️ File too large! The maximum size allowed is 1GB.");
    input.value = ""; // Clear the input
  } else {
    input.form.submit(); // Automatically upload the file
  }
}

// 4️⃣ Close menus when clicking outside
document.addEventListener("click", (event) => {
  const menu = document.getElementById("menu");
  const sortBox = document.getElementById("sortDropdown");

  // Check if the user clicked inside menu/sort areas
  const insideMenu = menu && menu.contains(event.target);
  const insideSort = sortBox && sortBox.contains(event.target);
  const burgerClicked = event.target.closest(".burger-btn");
  const sortClicked = event.target.closest(".sort-btn");

  // If clicked outside the menu → close it
  if (menu && !insideMenu && !burgerClicked) {
    menu.classList.remove("active");
  }

  // If clicked outside the sort box → close it
  if (sortBox && !insideSort && !sortClicked) {
    sortBox.classList.remove("active");
  }
});
