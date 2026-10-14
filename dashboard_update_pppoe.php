    } else if (type == 'pppoe') {
        $('#modalTitle').html('<i class="fas fa-users me-2"></i> Detail Users PPPoE Online (Mikrotik)');
        $('#modalBody').html('<div class="text-center"><div class="spinner-border text-primary"></div><p>Mengambil data dari Mikrotik...</p></div>');
        
        $.ajax({
            url: 'api/get_pppoe_count.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.users && response.users.length > 0) {
                    var html = '<div class="alert alert-success">Total User Online: <strong>' + response.total + '</strong> dari ' + response.routers_count + ' router</div>';
                    html += '<div class="table-responsive"><table class="table table-bordered">';
                    html += '<thead class="table-light"><tr><th>Username</th><th>IP Address</th><th>Uptime</th><th>Router</th></tr></thead><tbody>';
                    
                    $.each(response.users, function(i, user) {
                        html += '<tr>';
                        html += '<td><strong>' + user.username + '</strong></td>';
                        html += '<td><code>' + user.ip_address + '</code></td>';
                        html += '<td>' + user.uptime + '</td>';
                        html += '<td><span class="badge bg-primary">' + user.router + '</span></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    $('#modalBody').html(html);
                } else {
                    $('#modalBody').html('<div class="alert alert-warning">Tidak ada user PPPoE yang sedang online.</div>');
                }
            },
            error: function() {
                $('#modalBody').html('<div class="alert alert-danger">Gagal mengambil data dari Mikrotik. Pastikan router terhubung.</div>');
            }
        });
    }
