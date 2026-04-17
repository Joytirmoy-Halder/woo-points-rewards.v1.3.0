/**
 * WooPoints Admin Dashboard JavaScript
 *
 * Handles:
 * - Grand raffle pie-chart wheel (Canvas)
 * - Weighted spin animation
 * - AJAX calls for spin, save points, save prize
 * - Edit points modal
 * - Toast notifications
 */

(function ($) {
    'use strict';

    // ═══════════════════════════════
    // Raffle Wheel Class
    // ═══════════════════════════════
    class RaffleWheel {
        constructor(canvasId, users) {
            this.canvas = document.getElementById(canvasId);
            if (!this.canvas) return;

            this.ctx = this.canvas.getContext('2d');
            this.users = users || [];
            this.currentAngle = 0;
            this.isSpinning = false;
            this.winner = null;

            // Calculate total points for proportional slices
            this.totalPoints = this.users.reduce((sum, u) => sum + u.points, 0);

            // Pre-calculate slice angles
            this.slices = [];
            let startAngle = 0;

            for (const user of this.users) {
                const sliceAngle = (user.points / this.totalPoints) * (2 * Math.PI);
                this.slices.push({
                    ...user,
                    startAngle: startAngle,
                    endAngle: startAngle + sliceAngle,
                    midAngle: startAngle + sliceAngle / 2,
                });
                startAngle += sliceAngle;
            }

            this.draw();
            this.buildLegend();
        }

        /**
         * Draw the wheel on the canvas
         */
        draw() {
            const ctx = this.ctx;
            const cx = this.canvas.width / 2;
            const cy = this.canvas.height / 2;
            const radius = Math.min(cx, cy) - 10;

            ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

            if (this.slices.length === 0) {
                // Empty state
                ctx.beginPath();
                ctx.arc(cx, cy, radius, 0, 2 * Math.PI);
                ctx.fillStyle = '#2a2a3e';
                ctx.fill();
                ctx.strokeStyle = 'rgba(255,255,255,0.1)';
                ctx.lineWidth = 2;
                ctx.stroke();

                ctx.fillStyle = '#666';
                ctx.font = '16px -apple-system, BlinkMacSystemFont, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('No users with points', cx, cy);
                return;
            }

            // Draw slices
            for (const slice of this.slices) {
                const actualStart = slice.startAngle + this.currentAngle;
                const actualEnd = slice.endAngle + this.currentAngle;

                // Pie slice
                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.arc(cx, cy, radius, actualStart, actualEnd);
                ctx.closePath();

                ctx.fillStyle = slice.color;
                ctx.fill();

                // Slice border
                ctx.strokeStyle = 'rgba(0, 0, 0, 0.3)';
                ctx.lineWidth = 2;
                ctx.stroke();

                // Label
                const midAngle = actualStart + (actualEnd - actualStart) / 2;
                const sliceAngleSize = actualEnd - actualStart;
                const labelRadius = radius * 0.65;
                const labelX = cx + Math.cos(midAngle) * labelRadius;
                const labelY = cy + Math.sin(midAngle) * labelRadius;

                // Only draw label if slice is big enough
                if (sliceAngleSize > 0.15) {
                    ctx.save();
                    ctx.translate(labelX, labelY);
                    ctx.rotate(midAngle + Math.PI / 2);

                    // Flip text if it would be upside down
                    if (midAngle > Math.PI / 2 && midAngle < 3 * Math.PI / 2) {
                        ctx.rotate(Math.PI);
                    }

                    // Determine font size based on slice size
                    const fontSize = Math.min(14, Math.max(9, sliceAngleSize * 20));
                    ctx.font = `bold ${fontSize}px -apple-system, BlinkMacSystemFont, sans-serif`;
                    ctx.fillStyle = this.getContrastColor(slice.color);
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';

                    // Truncate name if needed
                    const maxChars = Math.max(5, Math.floor(sliceAngleSize * 30));
                    let name = slice.display_name;
                    if (name.length > maxChars) {
                        name = name.substring(0, maxChars - 1) + '…';
                    }

                    // Draw text with shadow for readability
                    ctx.shadowColor = 'rgba(0,0,0,0.5)';
                    ctx.shadowBlur = 3;
                    ctx.fillText(name, 0, -6);

                    // Points below name
                    ctx.font = `${Math.max(8, fontSize - 3)}px -apple-system, BlinkMacSystemFont, sans-serif`;
                    ctx.fillStyle = this.getContrastColor(slice.color, 0.8);
                    ctx.fillText(this.formatNumber(slice.points) + ' pts', 0, 8);

                    ctx.restore();
                }
            }

            // Center circle
            ctx.beginPath();
            ctx.arc(cx, cy, 35, 0, 2 * Math.PI);
            const gradient = ctx.createRadialGradient(cx, cy, 0, cx, cy, 35);
            gradient.addColorStop(0, '#2a2a3e');
            gradient.addColorStop(1, '#1a1a2e');
            ctx.fillStyle = gradient;
            ctx.fill();
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.15)';
            ctx.lineWidth = 2;
            ctx.stroke();

            // Center text
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('🎯', cx, cy);

            // Outer ring glow
            ctx.beginPath();
            ctx.arc(cx, cy, radius + 2, 0, 2 * Math.PI);
            ctx.strokeStyle = 'rgba(102, 126, 234, 0.3)';
            ctx.lineWidth = 4;
            ctx.stroke();
        }

        /**
         * Get contrasting text color
         */
        getContrastColor(hexColor, opacity = 1) {
            const hex = hexColor.replace('#', '');
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            const brightness = (r * 299 + g * 587 + b * 114) / 1000;
            const color = brightness > 128 ? '0, 0, 0' : '255, 255, 255';
            return `rgba(${color}, ${opacity})`;
        }

        /**
         * Format large numbers
         */
        formatNumber(num) {
            if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
            if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
            return Math.round(num).toString();
        }

        /**
         * Build the legend sidebar
         */
        buildLegend() {
            const legendEl = document.getElementById('wpr-wheel-legend');
            if (!legendEl) return;

            legendEl.innerHTML = '';

            if (this.slices.length === 0) {
                legendEl.innerHTML = '<p style="color: #888; font-size: 13px;">No participants yet.</p>';
                return;
            }

            // Sort by points descending for legend
            const sorted = [...this.slices].sort((a, b) => b.points - a.points);

            for (const slice of sorted) {
                const percentage = ((slice.points / this.totalPoints) * 100).toFixed(1);
                const item = document.createElement('div');
                item.className = 'wpr-legend-item';
                item.innerHTML = `
                    <span class="wpr-legend-color" style="background: ${slice.color};"></span>
                    <span class="wpr-legend-name">${slice.display_name}</span>
                    <span class="wpr-legend-points">${this.formatNumber(slice.points)} pts (${percentage}%)</span>
                `;
                legendEl.appendChild(item);
            }
        }

        /**
         * Spin the wheel to land on a specific winner (determined by server)
         * @param {number} winnerUserId - user_id from server
         * @param {Function} onComplete - callback with winner slice data
         */
        spin(winnerUserId, onComplete) {
            if (this.isSpinning || this.slices.length === 0) return;

            this.isSpinning = true;
            this.winner = null;

            // Find the winner slice by user_id from server response
            const winnerSlice = this.slices.find(s => s.user_id === winnerUserId);
            if (!winnerSlice) {
                this.isSpinning = false;
                if (typeof onComplete === 'function') onComplete(null);
                return;
            }

            // Calculate target angle so the pointer (top, -π/2) lands on the winner's slice
            const winnerMidAngle = (winnerSlice.startAngle + winnerSlice.endAngle) / 2;

            // Ensure enough forward rotation (at least 5 full spins)
            let targetRotation = (-Math.PI / 2) - winnerMidAngle;
            while (targetRotation < 10 * Math.PI) {
                targetRotation += 2 * Math.PI;
            }

            const startAngle = this.currentAngle;
            const adjustedTarget = startAngle + targetRotation;
            const duration = 6000 + Math.random() * 2000; // 6-8 seconds
            const startTime = performance.now();

            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Easing: cubic ease-out for natural deceleration
                const eased = 1 - Math.pow(1 - progress, 4);

                this.currentAngle = startAngle + (adjustedTarget - startAngle) * eased;
                this.draw();

                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    this.currentAngle = this.currentAngle % (2 * Math.PI);
                    this.isSpinning = false;
                    this.winner = winnerSlice;

                    if (typeof onComplete === 'function') {
                        onComplete(winnerSlice);
                    }
                }
            };

            requestAnimationFrame(animate);
        }
    }

    // ═══════════════════════════════
    // Initialize on DOM ready
    // ═══════════════════════════════
    $(document).ready(function () {
        if (typeof wprAdmin === 'undefined') return;

        // Initialize the raffle wheel
        let wheel = null;
        if (document.getElementById('wpr-raffle-wheel')) {
            wheel = new RaffleWheel('wpr-raffle-wheel', wprAdmin.wheel_users);
        }

        // ─── Spin Button ─────────────────
        $('#wpr-spin-btn').on('click', function () {
            if (!wheel || wheel.slices.length === 0) {
                showToast(wprAdmin.i18n.no_users, 'error');
                return;
            }

            if (!confirm(wprAdmin.i18n.confirm_spin)) {
                return;
            }

            const $btn = $(this);
            $btn.prop('disabled', true);
            $btn.find('.wpr-spin-text').text(wprAdmin.i18n.spinning);
            $('#wpr-winner-display').hide();

            // Step 1: Ask server for the winner
            $.ajax({
                url: wprAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpr_spin_raffle',
                    nonce: wprAdmin.nonce,
                },
                success: function (response) {
                    if (response.success) {
                        const data = response.data;

                        // Step 2: Animate the wheel to land on the server-picked winner
                        wheel.spin(data.winner_id, function (winner) {
                            if (winner) {
                                $('#wpr-winner-display').fadeIn();
                                $('.wpr-winner-name').text('🎊 ' + data.winner_name);
                                $('.wpr-winner-email').text(data.winner_email);
                                $('.wpr-winner-points').text(data.winner_points + ' points');
                            }
                            showToast(data.message);

                            // Step 3: Reload page after 3.5s to sync history table (BUG-005 fix)
                            setTimeout(() => location.reload(), 3500);
                        });
                    } else {
                        showToast(response.data.message || wprAdmin.i18n.spin_error, 'error');
                        $btn.prop('disabled', false);
                        $btn.find('.wpr-spin-text').text('SPIN THE WHEEL');
                    }
                },
                error: function () {
                    showToast(wprAdmin.i18n.spin_error, 'error');
                    $btn.prop('disabled', false);
                    $btn.find('.wpr-spin-text').text('SPIN THE WHEEL');
                },
            });
        });

        // ─── Save Prize Details ──────────
        $('#wpr-save-prize').on('click', function () {
            const $btn = $(this);
            const prizeName = $('#wpr-prize-name').val();
            const prizeDesc = $('#wpr-prize-desc').val();

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: wprAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpr_save_prize_details',
                    nonce: wprAdmin.nonce,
                    prize_name: prizeName,
                    prize_desc: prizeDesc,
                },
                success: function (response) {
                    if (response.success) {
                        showToast(response.data.message);
                    } else {
                        showToast(response.data.message || 'Error saving.', 'error');
                    }
                },
                error: function () {
                    showToast('Network error.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Save Prize Details');
                },
            });
        });

        // ─── Edit Points Modal ───────────
        $(document).on('click', '.wpr-btn-edit', function () {
            const userId = $(this).data('user-id');
            const points = $(this).data('points');
            const name = $(this).data('name');

            $('#wpr-edit-user-id').val(userId);
            $('#wpr-edit-points').val(points);
            $('.wpr-modal-user-name').text(name);
            $('#wpr-edit-modal').fadeIn(200);
        });

        // Close modal
        $(document).on('click', '.wpr-modal-close, .wpr-modal-close-btn, .wpr-modal-backdrop', function () {
            $('#wpr-edit-modal').fadeOut(200);
        });

        // Save points
        $('#wpr-save-points').on('click', function () {
            const userId = $('#wpr-edit-user-id').val();
            const points = $('#wpr-edit-points').val();
            const $btn = $(this);

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: wprAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpr_save_user_points',
                    nonce: wprAdmin.nonce,
                    user_id: userId,
                    points: points,
                },
                success: function (response) {
                    if (response.success) {
                        showToast(response.data.message);
                        // Refresh the page to update the table
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(response.data.message || 'Error saving.', 'error');
                    }
                },
                error: function () {
                    showToast('Network error.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Save Changes');
                    $('#wpr-edit-modal').fadeOut(200);
                },
            });
        });

        // ESC to close modal
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') {
                $('#wpr-edit-modal').fadeOut(200);
            }
        });

        // ─── Manual Add Points: Submit ───────────
        $('#wpr-manual-add-btn').on('click', function () {
            const userId = $('#wpr-manual-user-select').val();
            const points = $('#wpr-manual-points').val();
            const $btn = $(this);

            if (!userId) {
                showToast('Please select a user first.', 'error');
                return;
            }

            if (!points || parseFloat(points) <= 0) {
                showToast('Please enter a valid points amount.', 'error');
                return;
            }

            $btn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: wprAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'wpr_manual_add_points',
                    nonce: wprAdmin.nonce,
                    user_id: userId,
                    points: points,
                },
                success: function (response) {
                    if (response.success) {
                        showToast(response.data.message);
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        showToast(response.data.message || 'Error adding points.', 'error');
                    }
                },
                error: function () {
                    showToast('Network error.', 'error');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Add Points');
                },
            });
        });
    });

    // ═══════════════════════════════
    // Toast Notification
    // ═══════════════════════════════
    function showToast(message, type = 'success') {
        // Remove existing toasts
        $('.wpr-toast').remove();

        const $toast = $('<div class="wpr-toast"></div>')
            .text(message)
            .addClass(type === 'error' ? 'wpr-toast-error' : '');

        $('body').append($toast);

        // Auto remove after animation
        setTimeout(() => $toast.remove(), 3500);
    }

})(jQuery);
