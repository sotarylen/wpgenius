
//读取本地txt文件并加载到浏览器中，以GB2312的编码方式打开。
const loadFiles = () => {
  const fileInput = document.createElement("input");
  fileInput.type = "file";
  fileInput.multiple = true;
  fileInput.webkitdirectory = false; // 启用webkitdirectory属性
  fileInput.onchange = () => {
    const fileList = fileInput.files;
    const fileMenu = document.getElementById("fileMenu");
    fileMenu.innerHTML = "";

    const fileNames = Array.from(fileList)
      .filter(
        (file) =>
          file.type === "text/plain" || file.type === "application/pdf"
      )
      .map((file) => file.name.split(".")[0])
      .sort();

    fileNames.forEach((fileName) => {
      const file = Array.from(fileList).find(
        (file) => file.name.split(".")[0] === fileName
      );
      if (!file) {
        return;
      }
      const listItem = document.createElement("li");
      listItem.innerHTML = fileName;
      listItem.file = file;
      listItem.onclick = () => {
        const selectedFile = listItem.file;
        if (selectedFile.type === "application/pdf") {
          const embed = document.createElement("embed");
          embed.src = URL.createObjectURL(selectedFile);
          embed.type = "application/pdf";
          embed.width = "100%";
          embed.height = "100%";
          const container = document.getElementById("fileContent");
          container.innerHTML = "";
          container.style.height = "100%";
          container.appendChild(embed);
        } else {
          const reader = new FileReader();
          reader.onload = () => {
            const fileContent = reader.result;
            const gb2312_content = new TextDecoder("gb2312").decode(
              new Uint8Array(fileContent)
            );
            const pre = document.createElement("pre");
            pre.innerHTML = gb2312_content;
            const container = document.getElementById("fileContent");
            container.innerHTML = "";
            container.appendChild(pre); // 先添加pre元素到容器中
            const lineHeightInButton = document.getElementById("line-height-in"); // 获取行高控制按钮元素
            const lineHeightOutButton = document.getElementById("line-height-out");
            const minLineHeight = 25; // 最小行高
            const maxLineHeight = 50; // 最大行高

            lineHeightInButton.onclick = function () {
              const currentLineHeight = parseFloat(getComputedStyle(pre).lineHeight);
              const newLineHeight = Math.min(currentLineHeight + 5, maxLineHeight);
              pre.style.lineHeight = newLineHeight + "px";
            };

            lineHeightOutButton.onclick = function () {
              const currentLineHeight = parseFloat(getComputedStyle(pre).lineHeight);
              const newLineHeight = Math.max(currentLineHeight - 5, minLineHeight);
              pre.style.lineHeight = newLineHeight + "px";
            };
          };
          reader.readAsArrayBuffer(selectedFile);
        }
        localStorage.setItem("currentFile", selectedFile.name);

        // 将当前正在查看的文件的名称的容器li设置为li:active
        const activeItem = document.querySelector("#fileMenu li.ac");
        if (activeItem) {
          activeItem.classList.remove("ac");
        }
        listItem.classList.add("ac");
      };
      fileMenu.appendChild(listItem);
    });

    // 自动读取列表中的第一个文件内容到fileContent容器中
    const firstFile = fileMenu.firstChild;
    if (firstFile) {
      firstFile.click();
    }

    // 控制文字大小
    var zoomInButton = document.getElementById("zoom-in");
    var zoomOutButton = document.getElementById("zoom-out");
    const content = document.getElementById("fileContent");
    zoomInButton.onclick = function () {
      var currentZoom = parseFloat(getComputedStyle(content).zoom);
      content.style.zoom = currentZoom + 0.2;
    };
    zoomOutButton.onclick = function () {
      var currentZoom = parseFloat(getComputedStyle(content).zoom);
      content.style.zoom = currentZoom - 0.2;
    };



    // 获取复制按钮和文件内容容器
    var copyButton = document.getElementById("copyButton");
    var fileContent = document.getElementById("fileContent");

    // 点击复制按钮时触发的函数
    copyButton.onclick = function () {
      // 创建一个新的 textarea 元素用于存储文件内容
      var textarea = document.createElement("textarea");
      textarea.value = fileContent.innerText;
      document.body.appendChild(textarea);
      // 选中 textarea 中的内容
      textarea.select();
      // 将选中的内容复制到剪贴板中
      navigator.clipboard.writeText(textarea.value).then(
        function () {
          // 成功复制后的操作，将按钮标签改为 Copied
          copyButton.innerHTML = "Content Copied";
          // 2秒后将按钮标签改为 Copy
          setTimeout(function () {
            copyButton.innerHTML = "Copy fileContent";
          }, 2000);
        },
        function () {
          // 复制失败后的操作
          if (window.WPGenius && window.WPGenius.UI) {
            WPGenius.UI.toast("Copy failed!", 'error');
          }
        }
      );
      // 删除 textarea 元素
      document.body.removeChild(textarea);
    };
  };
  fileInput.click();
};

/*
//控制背景颜色
var colorPicker = document.getElementById("color-picker");
var contentContainerColor = document.getElementById('contentContainer');
colorPicker.addEventListener("input", function() {
  var color = colorPicker.value;
  contentContainerColor.style.backgroundColor = color;
  var brightness = calculateBrightness(color);
  if (brightness < 90) {
      contentContainer.style.color = "white";
  } else {
      contentContainer.style.color = "black";
  }
  });
  //定义背景亮度的计算方法
  function calculateBrightness(color) {
      var hexColor = color.replace("#", "");
      var red = parseInt(hexColor.substring(0, 2), 16);
      var green = parseInt(hexColor.substring(2, 4), 16);
      var blue = parseInt(hexColor.substring(4, 6), 16);
      var brightness = Math.round(((red * 299) + (green * 587) + (blue * 114)) / 1000);
      return brightness;
    }
 

// 设置背景图片
var imagePicker = document.getElementById("imagePicker");
var contentContainerImage = document.getElementById('contentContainer');
  imagePicker.addEventListener("click", function() {
  var input = document.createElement("input");
  input.type = "file";
  input.accept = "image/*";
  input.onchange = function() {
    var file = this.files[0];
    var reader = new FileReader();
    reader.onload = function() {
      contentContainerImage.style.backgroundImage = "url(" + reader.result + ")";
    };
    reader.readAsDataURL(file);
  };
  input.click();
  });
*/
