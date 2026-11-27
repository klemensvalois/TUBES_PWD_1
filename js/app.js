(function () {
    function ambilElemen(id) {
        return document.getElementById(id);
    }

    function perbaruiCountdown() {
        var spans = document.getElementsByTagName("span");
        var sekarang = new Date().getTime();
        for (var i = 0; i < spans.length; i++) {
            var target = spans[i].getAttribute("data-dl");
            if (!target) {
                continue;
            }
            var sisaDetik = Math.floor((parseInt(target, 10) - sekarang) / 1000);
            if (sisaDetik <= 0) {
                spans[i].innerHTML = "Lewat";
            } else {
                var hari = Math.floor(sisaDetik / 86400);
                var jam  = Math.floor((sisaDetik % 86400) / 3600);
                var mnt  = Math.floor((sisaDetik % 3600) / 60);
                var dtk  = sisaDetik % 60;
                spans[i].innerHTML = hari + "h " + jam + "j " + mnt + "m " + dtk + "d";
            }
        }
    }

    perbaruiCountdown();
    setInterval(perbaruiCountdown, 1000);

    document.onclick = function (ev) {
        var eventObj = ev || window.event;
        var target = eventObj.target || eventObj.srcElement;
        var kelas = target && target.className ? String(target.className) : "";
        if (kelas.indexOf("del") > -1 && !confirm("Yakin menghapus data ini?")) {
            return false;
        }
    };

    var inputFilter = ambilElemen("filter");
    if (inputFilter) {
        inputFilter.onkeyup = function () {
            var kataKunci = inputFilter.value.toLowerCase();
            var baris = document.getElementsByTagName("tr");
            var tautan = document.getElementsByTagName("a");

            for (var i = 0; i < baris.length; i++) {
                if (baris[i].className.indexOf("row") > -1) {
                    var cocokBaris = baris[i].innerHTML.toLowerCase().indexOf(kataKunci) > -1;
                    baris[i].style.display = cocokBaris ? "" : "none";
                }
            }
            for (var j = 0; j < tautan.length; j++) {
                if (tautan[j].className.indexOf("dl-item") > -1) {
                    var cocokItem = tautan[j].innerHTML.toLowerCase().indexOf(kataKunci) > -1;
                    tautan[j].style.display = cocokItem ? "" : "none";
                }
            }
        };
    }

    window.openM = function () {
        var modalOv = ambilElemen("ov");
        if (modalOv) {
            modalOv.className = "ov show";
        }
    };

    window.closeM = function () {
        var modalOv = ambilElemen("ov");
        if (modalOv) {
            modalOv.className = "ov";
        }
    };

    window.tab = function (nomorTab) {
        ambilElemen("t1").style.display = (nomorTab == 1) ? "block" : "none";
        ambilElemen("t2").style.display = (nomorTab == 2) ? "block" : "none";
        ambilElemen("k1").className = (nomorTab == 1) ? "on" : "";
        ambilElemen("k2").className = (nomorTab == 2) ? "on" : "";
    };

    var semuaBaris = document.getElementsByTagName("tr");
    var kartuDetail = ambilElemen("detail");
    if (kartuDetail) {
        for (var i = 0; i < semuaBaris.length; i++) {
            (function (barisAktif) {
                if (barisAktif.className.indexOf("row") < 0) {
                    return;
                }
                barisAktif.onclick = function () {
                    for (var j = 0; j < semuaBaris.length; j++) {
                        semuaBaris[j].className = semuaBaris[j].className.replace(" hl", "");
                    }
                    barisAktif.className += " hl";

                    function ambilData(atribut) {
                        return barisAktif.getAttribute("data-" + atribut) || "-";
                    }

                    ambilElemen("d_id").innerHTML = ambilData("id");
                    ambilElemen("d_av").innerHTML = ambilData("ini");
                    ambilElemen("d_nama").innerHTML = ambilData("nama");
                    ambilElemen("d_sub").innerHTML = ambilData("id");
                    ambilElemen("d_fak").innerHTML = ambilData("fak");
                    ambilElemen("d_prodi").innerHTML = ambilData("prodi");
                    ambilElemen("d_umur").innerHTML = ambilData("umur");
                    ambilElemen("d_gen").innerHTML = ambilData("gen");
                    ambilElemen("d_edit").href = ambilData("edit");
                    ambilElemen("d_i").value = ambilData("pk");
                    kartuDetail.style.display = "block";
                };
            })(semuaBaris[i]);
        }
    }

    window.ed = function (idField) {
        var field = ambilElemen(idField);
        if (!field) {
            return;
        }
        field.removeAttribute("readonly");
        field.className = "edit";
        field.focus();
        var tombolSimpan = ambilElemen("sv");
        if (tombolSimpan) {
            tombolSimpan.style.display = "inline-block";
        }
    };
    // --- Deteksi NPM/Email & validasi form registrasi (Bonus 3) ---
    var formReg = ambilElemen("form-register");
    if (formReg) {
        var inNama   = ambilElemen("reg_nama");
        var inNpm    = ambilElemen("reg_npm");
        var inEmail  = ambilElemen("reg_email");
        var inPw     = ambilElemen("reg_pw");
        var msgNpm   = ambilElemen("pesan_npm");
        var msgEmail = ambilElemen("pesan_email");
        var btnReg   = ambilElemen("btn_daftar");

        var npmDipakai = false;
        var emailDipakai = false;

        function aturTombolDaftar() {
            if (btnReg) {
                btnReg.disabled = (npmDipakai || emailDipakai);
            }
        }

        function cekDataRegistrasi(tipe, nilai, elPesan) {
            if (!elPesan) {
                return;
            }
            var bersih = nilai.replace(/^\s+|\s+$/g, "");
            if (bersih === "") {
                elPesan.innerHTML = "";
                if (tipe === "npm") npmDipakai = false;
                if (tipe === "email") emailDipakai = false;
                aturTombolDaftar();
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open("GET", "cek_akun.php?" + tipe + "=" + encodeURIComponent(bersih), true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    var hasil = xhr.responseText.replace(/^\s+|\s+$/g, "");
                    if (hasil === "dipakai") {
                        elPesan.innerHTML = (tipe === "npm" ? "NPM" : "Email") + " sudah dipakai!";
                        elPesan.style.color = "red";
                        if (tipe === "npm") npmDipakai = true;
                        if (tipe === "email") emailDipakai = true;
                    } else if (hasil === "tersedia") {
                        elPesan.innerHTML = (tipe === "npm" ? "NPM" : "Email") + " tersedia.";
                        elPesan.style.color = "green";
                        if (tipe === "npm") npmDipakai = false;
                        if (tipe === "email") emailDipakai = false;
                    }
                    aturTombolDaftar();
                }
            };
            xhr.send();
        }

        if (inNpm) {
            inNpm.onblur = function () {
                cekDataRegistrasi("npm", inNpm.value, msgNpm);
            };
        }

        if (inEmail) {
            inEmail.onblur = function () {
                cekDataRegistrasi("email", inEmail.value, msgEmail);
            };
        }

        formReg.onsubmit = function () {
            if (inNama.value === "" || inNpm.value === "" || inEmail.value === "" || inPw.value === "") {
                alert("Nama, NPM, email, dan password wajib diisi!");
                return false;
            }
            if (npmDipakai || emailDipakai) {
                alert("NPM atau email sudah dipakai, silakan gunakan yang lain.");
                return false;
            }
            return true;
        };
    }
    // --- Menu Foto Profil & Crop Lingkaran (Drag + Zoom) ---
    var btnAvatar = ambilElemen("btn-avatar");
    var menuFoto  = ambilElemen("menu-foto");

    if (btnAvatar && menuFoto) {
        var inputFile   = ambilElemen("input-foto-file");
        var aksiLihat   = ambilElemen("aksi-lihat");
        var aksiUnggah  = ambilElemen("aksi-unggah");
        var aksiHapus   = ambilElemen("aksi-hapus");

        var modalCrop   = ambilElemen("modal-crop");
        var cropStage   = ambilElemen("crop-stage");
        var cropImg     = ambilElemen("crop-img");
        var btnTutup    = ambilElemen("btn-tutup-crop");
        var btnReupload = ambilElemen("btn-unggah-ulang");
        var btnZoomIn   = ambilElemen("btn-zoom-in");
        var btnZoomOut  = ambilElemen("btn-zoom-out");
        var btnSimpan   = ambilElemen("btn-simpan-crop");

        var formCrop    = ambilElemen("form-simpan-crop");
        var inputCrop   = ambilElemen("foto_crop");
        var formHapus   = ambilElemen("form-hapus-foto");

        var modalLihat  = ambilElemen("modal-lihat");
        var btnTutupLht = ambilElemen("btn-tutup-lihat");

        // Ukuran area crop (harus cocok dengan CSS .crop-stage & .crop-circle)
        var stageW   = 380;
        var stageH   = 300;
        var cropSize = 240;
        var circleX  = 70;
        var circleY  = 30;

        var natW = 0, natH = 0;
        var scale = 1, minScale = 1;
        var imgX = 0, imgY = 0;
        var sedangSeret = false;
        var awalX = 0, awalY = 0, simpanX = 0, simpanY = 0;

        function batasiPosisi() {
            var dispW = natW * scale;
            var dispH = natH * scale;
            var minX  = circleX + cropSize - dispW;
            var minY  = circleY + cropSize - dispH;

            if (imgX > circleX) imgX = circleX;
            if (imgX < minX)    imgX = minX;
            if (imgY > circleY) imgY = circleY;
            if (imgY < minY)    imgY = minY;
        }

        function renderPosisi() {
            cropImg.style.width  = Math.round(natW * scale) + "px";
            cropImg.style.height = Math.round(natH * scale) + "px";
            cropImg.style.left   = Math.round(imgX) + "px";
            cropImg.style.top    = Math.round(imgY) + "px";
        }

        function ubahZoom(faktor) {
            var skalaBaru = scale * faktor;
            if (skalaBaru < minScale)     skalaBaru = minScale;
            if (skalaBaru > minScale * 3) skalaBaru = minScale * 3;

            var cx   = stageW / 2;
            var cy   = stageH / 2;
            var relX = (cx - imgX) / scale;
            var relY = (cy - imgY) / scale;

            scale = skalaBaru;
            imgX  = cx - relX * scale;
            imgY  = cy - relY * scale;

            batasiPosisi();
            renderPosisi();
        }

        // Buka / tutup menu popup saat avatar diklik
        btnAvatar.onclick = function (e) {
            if (e && e.stopPropagation) e.stopPropagation();
            if (menuFoto.className.indexOf("tampil") > -1) {
                menuFoto.className = "menu-foto";
            } else {
                menuFoto.className = "menu-foto tampil";
            }
        };

        // Tutup menu popup jika klik di luar
        document.addEventListener("click", function () {
            menuFoto.className = "menu-foto";
        });

        menuFoto.onclick = function (e) {
            if (e && e.stopPropagation) e.stopPropagation();
        };

        // Tombol Unggah foto & Unggah ulang
        function bukaPilihFile() {
            menuFoto.className = "menu-foto";
            inputFile.value = "";
            inputFile.click();
        }

        aksiUnggah.onclick = bukaPilihFile;
        btnReupload.onclick = bukaPilihFile;

        // Saat gambar dipilih dari komputer
        inputFile.onchange = function () {
            if (!inputFile.files || !inputFile.files[0]) return;
            var file = inputFile.files[0];

            if (file.type.indexOf("image/") !== 0) {
                alert("Silakan pilih file gambar (JPG atau PNG).");
                return;
            }

            var reader = new FileReader();
            reader.onload = function (ev) {
                cropImg.onload = function () {
                    natW = cropImg.naturalWidth;
                    natH = cropImg.naturalHeight;
                    minScale = Math.max(cropSize / natW, cropSize / natH);
                    scale = minScale;
                    imgX = (stageW - natW * scale) / 2;
                    imgY = (stageH - natH * scale) / 2;
                    batasiPosisi();
                    renderPosisi();
                    modalCrop.className = "modal-bg tampil";
                };
                cropImg.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        };

        // Geser gambar (Drag)
        cropStage.onmousedown = function (e) {
            if (e.target && e.target.tagName === "BUTTON") return;
            if (e.preventDefault) e.preventDefault();
            sedangSeret = true;
            awalX   = e.clientX;
            awalY   = e.clientY;
            simpanX = imgX;
            simpanY = imgY;
        };

        document.addEventListener("mousemove", function (e) {
            if (!sedangSeret) return;
            imgX = simpanX + (e.clientX - awalX);
            imgY = simpanY + (e.clientY - awalY);
            batasiPosisi();
            renderPosisi();
        });

        document.addEventListener("mouseup", function () {
            sedangSeret = false;
        });

        // Tombol Zoom + dan -
        btnZoomIn.onclick  = function () { ubahZoom(1.15); };
        btnZoomOut.onclick = function () { ubahZoom(1 / 1.15); };

        // Tutup modal crop
        btnTutup.onclick = function () {
            modalCrop.className = "modal-bg";
        };

        // Klik centang hijau: potong gambar pakai canvas lalu kirim ke PHP
        btnSimpan.onclick = function () {
            var canvas = document.createElement("canvas");
            canvas.width  = 300;
            canvas.height = 300;
            var ctx = canvas.getContext("2d");

            ctx.fillStyle = "#ffffff";
            ctx.fillRect(0, 0, 300, 300);

            var sx    = (circleX - imgX) / scale;
            var sy    = (circleY - imgY) / scale;
            var sSize = cropSize / scale;

            ctx.drawImage(cropImg, sx, sy, sSize, sSize, 0, 0, 300, 300);
            inputCrop.value = canvas.toDataURL("image/jpeg", 0.9);
            formCrop.submit();
        };

        // Aksi Lihat foto
        if (aksiLihat && modalLihat) {
            aksiLihat.onclick = function () {
                menuFoto.className = "menu-foto";
                modalLihat.className = "modal-bg tampil";
            };
            btnTutupLht.onclick = function () {
                modalLihat.className = "modal-bg";
            };
            modalLihat.onclick = function (e) {
                if (e.target === modalLihat) {
                    modalLihat.className = "modal-bg";
                }
            };
        }

        // Aksi Hapus foto
        if (aksiHapus) {
            aksiHapus.onclick = function () {
                menuFoto.className = "menu-foto";
                if (confirm("Yakin ingin menghapus foto profil?")) {
                    formHapus.submit();
                }
            };
        }
    }
    // --- Menampilkan pesan error/sukses di index.xhtml ---
    var kotakPesanLogin = ambilElemen("pesan-login");
    if (kotakPesanLogin && window.location.search) {
        var query = window.location.search.substring(1).split("&");
        for (var q = 0; q < query.length; q++) {
            var bagian = query[q].split("=");
            var kunci  = bagian[0];
            var isi    = bagian[1] ? decodeURIComponent(bagian[1].replace(/\+/g, " ")) : "";

            if (kunci === "err" && isi !== "") {
                kotakPesanLogin.className = "flash bad";
                kotakPesanLogin.innerHTML = isi;
            } else if (kunci === "msg" && isi !== "") {
                kotakPesanLogin.className = "flash ok";
                kotakPesanLogin.innerHTML = isi;
            }
        }
    }
})();

    