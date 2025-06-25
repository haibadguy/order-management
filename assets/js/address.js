// Khởi tạo các select box địa chỉ
function initAddressSelects(provinceSelect, districtSelect, wardSelect, addressInput, callback) {
    let isLoading = false;

    // Load tỉnh/thành phố
    fetch('../api/address.php?action=provinces')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(provinces => {
            provinceSelect.innerHTML = '<option value="">Chọn Tỉnh/Thành phố</option>' +
                provinces.map(p => `<option value="${p.code}">${p.name}</option>`).join('');
        })
        .catch(error => {
            console.error('Error loading provinces:', error);
            provinceSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
        });

    // Sự kiện khi chọn tỉnh/thành phố
    provinceSelect.addEventListener('change', async function() {
        const provinceCode = this.value;
        districtSelect.innerHTML = '<option value="">Chọn Quận/Huyện</option>';
        wardSelect.innerHTML = '<option value="">Chọn Phường/Xã</option>';
        
        if (provinceCode) {
            try {
                isLoading = true;
                const response = await fetch(`../api/address.php?action=districts&code=${provinceCode}`);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const districts = await response.json();
                districtSelect.innerHTML = '<option value="">Chọn Quận/Huyện</option>' +
                    districts.map(d => `<option value="${d.code}">${d.name}</option>`).join('');
            } catch (error) {
                console.error('Error loading districts:', error);
                districtSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
            } finally {
                isLoading = false;
            }
        }
        updateAddress();
    });

    // Sự kiện khi chọn quận/huyện
    districtSelect.addEventListener('change', async function() {
        const districtCode = this.value;
        wardSelect.innerHTML = '<option value="">Chọn Phường/Xã</option>';
        
        if (districtCode) {
            try {
                isLoading = true;
                const response = await fetch(`../api/address.php?action=wards&code=${districtCode}`);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const wards = await response.json();
                wardSelect.innerHTML = '<option value="">Chọn Phường/Xã</option>' +
                    wards.map(w => `<option value="${w.code}">${w.name}</option>`).join('');
            } catch (error) {
                console.error('Error loading wards:', error);
                wardSelect.innerHTML = '<option value="">Lỗi tải dữ liệu</option>';
            } finally {
                isLoading = false;
            }
        }
        updateAddress();
    });

    // Sự kiện khi chọn phường/xã
    wardSelect.addEventListener('change', updateAddress);

    // Cập nhật địa chỉ đầy đủ
    function updateAddress() {
        const province = provinceSelect.selectedOptions[0]?.text || '';
        const district = districtSelect.selectedOptions[0]?.text || '';
        const ward = wardSelect.selectedOptions[0]?.text || '';
        
        // Bỏ qua các giá trị mặc định "Chọn..."
        const parts = [province, district, ward].filter(part => 
            part && !part.startsWith('Chọn ')
        );

        if (parts.length > 0) {
            const fullAddress = parts.join(', ');
            if (addressInput.tagName.toLowerCase() === 'input') {
                addressInput.value = fullAddress;
            } else {
                addressInput.textContent = fullAddress;
            }
            
            if (typeof callback === 'function') {
                callback({
                    province: { code: provinceSelect.value, name: province },
                    district: { code: districtSelect.value, name: district },
                    ward: { code: wardSelect.value, name: ward },
                    fullAddress: fullAddress
                });
            }
        }
    }

    // Hàm tìm và chọn option theo text hoặc value
    function selectOption(select, value, isCode = false) {
        if (!value) return false;
        const option = Array.from(select.options).find(opt => 
            isCode ? opt.value === value : opt.text.toLowerCase().trim() === value.toLowerCase().trim()
        );
        if (option) {
            select.value = option.value;
            return true;
        }
        return false;
    }

    // Trả về hàm để set giá trị
    return async function setAddress(provinceValue, districtValue, wardValue, isCode = false) {
        // Đợi load xong danh sách tỉnh/thành phố
        await new Promise(resolve => {
            const checkProvinces = () => {
                if (provinceSelect.options.length > 1) {
                    resolve();
                } else {
                    setTimeout(checkProvinces, 100);
                }
            };
            checkProvinces();
        });

        // Set tỉnh/thành phố
        if (selectOption(provinceSelect, provinceValue, isCode)) {
            provinceSelect.dispatchEvent(new Event('change'));

            // Đợi load xong danh sách quận/huyện
            await new Promise(resolve => {
                const checkDistricts = () => {
                    if (districtSelect.options.length > 1) {
                        resolve();
                    } else {
                        setTimeout(checkDistricts, 100);
                    }
                };
                setTimeout(checkDistricts, 500);
            });

            // Set quận/huyện
            if (selectOption(districtSelect, districtValue, isCode)) {
                districtSelect.dispatchEvent(new Event('change'));

                // Đợi load xong danh sách phường/xã
                await new Promise(resolve => {
                    const checkWards = () => {
                        if (wardSelect.options.length > 1) {
                            resolve();
                        } else {
                            setTimeout(checkWards, 100);
                        }
                    };
                    setTimeout(checkWards, 500);
                });

                // Set phường/xã
                if (selectOption(wardSelect, wardValue, isCode)) {
                    wardSelect.dispatchEvent(new Event('change'));
                }
            }
        }
    };
} 