document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.eme-arc-tabs .nav-tab');
    const contents = document.querySelectorAll('.eme-arc-tabs .tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();

            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            // Add active class to clicked tab
            this.classList.add('nav-tab-active');

            // Hide all content
            contents.forEach(content => content.style.display = 'none');
            // Show the selected tab content
            const target = this.getAttribute('href').substring(1); // Remove #
            document.getElementById(target).style.display = 'block';
        });
    });
});