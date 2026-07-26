document.addEventListener("DOMContentLoaded", function() {
    // ===== Student Status Doughnut =====
    new Chart(document.getElementById('studentsChart'), {
        type: 'doughnut',
        data: {
            labels: ['Pending','Active','Finished'],
            datasets: [{
                data: window.studentsData || [0,0,0],
                backgroundColor: ['#f87171', '#fca5a5', '#b91c1c'],
                borderColor: '#fff',
                borderWidth: 2
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // ===== Monthly Enrollments Bar =====
    new Chart(document.getElementById('enrollmentChart'), {
        type: 'bar',
        data: {
            labels: window.enrollmentMonths || [],
            datasets: [{
                label: 'Enrollments',
                data: window.monthlyEnrollments || [],
                backgroundColor: '#b91c1c'
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true } },
            plugins: { legend: { display: false } }
        }
    });

    // ===== Top Courses Bar =====
    new Chart(document.getElementById('topCoursesChart'), {
        type: 'bar',
        data: {
            labels: window.topCoursesTitles || [],
            datasets: [{
                label: 'Enrollments',
                data: window.topCoursesEnrollments || [],
                backgroundColor: '#f87171'
            }]
        },
        options: { responsive: true }
    });

    // ===== Revenue Line Chart =====
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: window.revenueMonths || [],
            datasets: [{
                label: 'Revenue ($)',
                data: window.revenueData || [],
                backgroundColor: 'rgba(244,63,94,0.2)',
                borderColor: '#b91c1c',
                fill: true,
                tension: 0.3
            }]
        },
        options: { responsive: true }
    });
});
