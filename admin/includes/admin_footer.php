<!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Admin Panel JavaScript -->
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Confirm deletion actions
        function confirmDelete(message = 'Bu işlemi gerçekleştirmek istediğinizden emin misiniz?') {
            return confirm(message);
        }

        // Show loading state on form submission
        function showLoading(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner"></span> İşleniyor...';
            button.disabled = true;
            
            // Re-enable after 10 seconds as safety
            setTimeout(function() {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 10000);
        }

        // Format number with Turkish locale
        function formatTurkishNumber(number, decimals = 2) {
            return new Intl.NumberFormat('tr-TR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(number);
        }

        // Real-time search functionality
        function initSearch(inputId, tableId) {
            const searchInput = document.getElementById(inputId);
            const table = document.getElementById(tableId);
            
            if (searchInput && table) {
                searchInput.addEventListener('keyup', function() {
                    const filter = this.value.toLowerCase();
                    const rows = table.getElementsByTagName('tr');
                    
                    for (let i = 1; i < rows.length; i++) {
                        const row = rows[i];
                        const cells = row.getElementsByTagName('td');
                        let found = false;
                        
                        for (let j = 0; j < cells.length; j++) {
                            const cell = cells[j];
                            if (cell.textContent.toLowerCase().indexOf(filter) > -1) {
                                found = true;
                                break;
                            }
                        }
                        
                        row.style.display = found ? '' : 'none';
                    }
                });
            }
        }

        // Auto-refresh page data every 30 seconds for dashboard
        if (window.location.pathname.includes('index.php')) {
            setInterval(function() {
                // Only refresh if user hasn't interacted for 5 minutes
                if ((Date.now() - lastUserActivity) > 300000) {
                    location.reload();
                }
            }, 30000);
        }

        // Track user activity
        let lastUserActivity = Date.now();
        document.addEventListener('click', function() {
            lastUserActivity = Date.now();
        });
        document.addEventListener('keypress', function() {
            lastUserActivity = Date.now();
        });

        // Copy to clipboard functionality
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success message
                const toast = document.createElement('div');
                toast.className = 'position-fixed top-0 end-0 p-3';
                toast.style.zIndex = '9999';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            <strong class="me-auto">Başarılı</strong>
                            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            Panoya kopyalandı!
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                
                setTimeout(function() {
                    toast.remove();
                }, 3000);
            });
        }

        // Status update functionality
        function updateStatus(url, data, callback) {
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(data => {
                if (callback) callback(data);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu!');
            });
        }

        // File upload preview
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                    document.getElementById(previewId).style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Price formatting for symbol management
        function formatPrice(price) {
            const num = parseFloat(price);
            if (isNaN(num)) return '0.00';
            
            if (num >= 1000) {
                return formatTurkishNumber(num, 2);
            } else if (num >= 1) {
                return formatTurkishNumber(num, 4);
            } else {
                return formatTurkishNumber(num, 8);
            }
        }
    </script>
</body>
</html>
