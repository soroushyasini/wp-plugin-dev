function openEditModal(id, name, description, type) {
  document.getElementById("editModal").classList.remove("hidden");
  document.getElementById("edit_project_id").value = id;
  document.getElementById("edit_name").value = name;
  document.getElementById("edit_description").value = description;
  document.getElementById("edit_type").value = type;
}

function closeEditModal() {
  document.getElementById("editModal").classList.add("hidden");
}

 function confirmArchive() {
    return confirm("با آرشیو پروژه، دسترسی به آن دیگر ممکن نیست. آیا مطمئن هستید؟");
  }

document.getElementById("share-form").onsubmit = async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  formData.append("_ajax_nonce", hamnaghsheh_ajax.nonce);
  const res = await fetch(hamnaghsheh_ajax.ajax_url, {
    method: "POST",
    body: new URLSearchParams([...formData, ["action", "create_share_link"]]),
  });
  const data = await res.json();
  if (data.success) {
    alert("✅ لینک ساخته شد: " + data.data.link);
    location.reload();
  } else {
    alert("❌ خطا: " + data.data);
  }
};

// savabegh
function translateActionType(action) {
  switch (action) {
    case "upload":
      return "آپلود اولیه";
    case "replace":
      return "جایگزینی فایل";
    case "delete":
      return "حذف فایل";
    case "download":
        return "دانلود";
    case "see" :
        return "مشاهده";
    default:
      return "نامشخص";
  }
}

function toJalali(dateString) {
  try {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    }).format(date);
  } catch (e) {
    console.warn("Persian date not supported, fallback to default format.");
    const date = new Date(dateString);
    return date.toLocaleString("fa-IR", {
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }
}

function openFileLogsModal(fileId) {
  const modal = document.getElementById("fileLogsModal");
  const content = document.getElementById("fileLogsContent");

  modal.classList.remove("hidden");
  modal.classList.add("flex");
  content.innerHTML =
    '<p class="text-center text-gray-400">در حال بارگذاری...</p>';

  const url =
    hamnaghsheh_ajax.ajax_url +
    "?action=get_file_logs" +
    "&file_id=" +
    encodeURIComponent(fileId) +
    "&_ajax_nonce=" +
    encodeURIComponent(hamnaghsheh_ajax.nonce);

  // ارسال درخواست AJAX برای گرفتن لاگ‌ها
  fetch(url)
    .then((response) => response.json())
    .then((data) => {
      if (data.success && data.logs.length > 0) {
        content.innerHTML = data.logs
          .map(
            (log) => `
          <div class="border rounded-lg p-2 bg-gray-50">
            <p><span class="font-semibold text-[#09375B]">عملیات:</span> ${translateActionType(
              log.action_type
            )}</p>
            <p><span class="font-semibold text-[#09375B]">کاربر:</span> ${
              log.user_name
            }</p>
            <p><span class="font-semibold text-[#09375B]">تاریخ:</span> ${toJalali(
              log.created_at
            )}</p>
          </div>`
          )
          .join("");
      } else {
        content.innerHTML =
          '<p class="text-center text-gray-400">هیچ سابقه‌ای یافت نشد.</p>';
      }
    })
    .catch(() => {
      content.innerHTML =
        '<p class="text-center text-red-500">خطا در دریافت اطلاعات.</p>';
    });
}

function closeFileLogsModal() {
  const modal = document.getElementById("fileLogsModal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

/// replace
document.querySelectorAll(".replace-btn").forEach((btn) => {
  btn.addEventListener("click", () => {
    const fileId = btn.dataset.fileId;
    document.getElementById("replace_file_id").value = fileId;
    document.getElementById("replaceModal").classList.remove("hidden");
    document.getElementById("replaceModal").classList.add("flex");
  });
});
document.getElementById("closeModalBtn").addEventListener("click", () => {
  document.getElementById("replaceModal").classList.add("hidden");
});

/// download
function downloadProjectFiles(projectId) {
  const url =
    hamnaghsheh_ajax.ajax_url +
    "?action=download_project_files" +
    "&project_id=" +
    encodeURIComponent(projectId) +
    "&_ajax_nonce=" +
    encodeURIComponent(hamnaghsheh_ajax.nonce);

  window.location.href = url;
}

function copyToClipboard(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const oldText = btn.textContent;
    btn.textContent = "کپی شد ✅";
    btn.classList.add("bg-green-100", "text-green-700");
    btn.classList.remove("bg-blue-100", "text-blue-700");
    setTimeout(() => {
      btn.textContent = oldText;
      btn.classList.add("bg-blue-100", "text-blue-700");
      btn.classList.remove("bg-green-100", "text-green-700");
    }, 2000);
  });
}

function logDownload(fileId, projectId) {
  fetch(hamnaghsheh_ajax.ajax_url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      action: "hamnaghsheh_log_download",
      file_id: fileId,
      project_id: projectId,
      _ajax_nonce: hamnaghsheh_ajax.nonce,
    }),
  })
  .then((r) => r.json())
  .then((data) => {
    if (!data.success) console.warn("خطا در ثبت لاگ دانلود");
  })
  .catch(() => console.warn("Ajax failed"));
}

function logSee(fileId, projectId) {
  fetch(hamnaghsheh_ajax.ajax_url, {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: new URLSearchParams({
      action: "hamnaghsheh_log_see",
      file_id: fileId,
      project_id: projectId,
      _ajax_nonce: hamnaghsheh_ajax.nonce,
    }),
  })
  .then((r) => r.json())
  .then((data) => {
    if (!data.success) console.warn("خطا در ثبت لاگ دانلود");
  })
  .catch(() => console.warn("Ajax failed"));
}

document.getElementById("open-share-popup").onclick = () => {
  document.getElementById("share-popup").classList.remove("hidden");
};
document.getElementById("close-share-popup").onclick = () => {
  document.getElementById("share-popup").classList.add("hidden");
};
