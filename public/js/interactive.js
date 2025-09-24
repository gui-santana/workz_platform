/**
 * Verifica se uma URL é válida.
 * @param {String} urlString - URL a ser verificada.
 * @returns {Boolean} Retorna verdadeiro se a URL for válida, falso caso contrário.
 */
const isValidUrl = (urlString) => {
  var urlPattern = new RegExp(
    '^https?:\/\/)?' + // validate protocol
      '((([a-z\d]([a-z\d-]*[a-z\d])*)\.)+[a-z]{2,}|' + // validate domain name
      '((\d{1,3}\.){3}\d{1,3}))' + // validate OR ip (v4) address
      '(\:\d+)?(\/[-a-z\d%_.~+]*)*' + // validate port and path
      '(\?[;&a-z\d%_.~+=-]*)?' + // validate query string
      '(\#[-a-z\d_]*)?$',
    'i'
  ); // validate fragment locator

  return !!urlPattern.test(urlString);
};

function disableSelection() {
  document.body.style.userSelect = "none";
  document.body.style.webkitUserSelect = "none";
  document.body.style.msUserSelect = "none";
}

function enableSelection() {
  document.body.style.userSelect = "";
  document.body.style.webkitUserSelect = "";
  document.body.style.msUserSelect = "";
}

/**
 * Cria uma nova janela interativa.
 * @param {String} target - Alvo da janela.
 * @param {String} id - ID da janela.
 * @param {String} icon - Ícone da janela.
 */
function newWindow(target, id, icon, title) {
  let iconSrc = resolveIconSrc(icon);

  var created = document.getElementsByClassName('window');
  var n = created.length;
  // Check if window is already opened
  var minimizedItem = document.getElementsByClassName('minimizedItem');
  var exists = 0;
  for (var i = 0; i < minimizedItem.length; i++) {
    if (minimizedItem[i].name == id) {
      exists = exists + 1;
      minimizedItem[i].click();
    }
  }
  if (exists == 0) {
    var windowEl = document.createElement("div");
    windowEl.setAttribute("name", id);
    windowEl.id = "window_" + (n + 1);
    windowEl.classList.add("window", "bg-white");
    document.getElementById("desktop").appendChild(windowEl);
    interactive(windowEl.id, {
      resize: true,
      drag: true,
      close: true,
      minMax: true,
      title: title,
      iconSrc: iconSrc
    });

    desktop();
    if (target !== null) {
      var root = document.createElement("div");
      root.classList.add(
        "w-full",
        "rounded-lg"
      );
      root.style.height = "calc(100% - 30px)"; // Adjust height for new header
      windowEl.appendChild(root);

      var iframe = document.createElement("iframe");
      iframe.src = target;
      iframe.classList.add(
        "rounded-lg",
        "border-none",
        "w-full",
        "h-full"
      );
      root.appendChild(iframe);

    }
  }
}

/**
 * Torna um elemento interativo (redimensionável, arrastável, minimizar, maximizar e fechar).
 * @param {String} id - ID do elemento.
 * @param {Object} config - Configurações personalizadas.
 */
function interactive(id, config) {
  let element = document.getElementById(id);
  element.classList.add("interactive");
  element.style.zIndex = "inherit";

  if (config === undefined) {
    // Configuração padrão
    resizable(element);
    draggable(element);
    close(element);
    minMax(element);
  } else {
    // Configuração personalizada
    if (config.resize !== false) {
      resizable(element);
    }
    if (config.drag !== false) {
      draggable(element, config.title, config.iconSrc);
    }
    if (config.close !== false) {
      close(element);
    }
    if (config.minMax !== false) {
      minMax(
        element,
        config.minZone,
        config.minMaxIcons,
        config.minDoubleClick
      );
    }
  }
  element.onmousedown = changeStackOrder;
}

/* ================= RESIZABLE ELEMENT ================= */

/**
 * Torna um elemento redimensionável.
 * Uso: clique com o botão esquerdo + arraste para redimensionar o elemento.
 * @param {HTMLElement} element
 */
function resizable(element) {
  element.classList.add("resizable");

  /* Adiciona um elemento pai que conterá o elemento e seus pontos de redimensionamento
     O elemento pai deve ficar entre o elemento e seu pai original */
  let resizeParent = createElementWithIdAndClassName(
    "div",
    "parent_" + element.id,
    "parentResize"
  );
  resizeParent.style.zIndex = 1;
  resizeParent.classList.add(
    "opacity-0",
    "transition-opacity", // Tailwind for transition: opacity
    "duration-300",     // A faster duration
    "ease-in-out",      // A nice easing function
    "rounded-md",
    "border",
    "overflow-hidden",
    "absolute",
    "grid"
  );

  element.parentElement.appendChild(resizeParent);
  resizeParent.appendChild(element);

  // Adiciona os pontos de redimensionamento
  addResizePoints(element, resizeParent);

  setTimeout(() => {
    resizeParent.classList.remove("opacity-0");
  }, 100);
}

/**
 * Adiciona os pontos de redimensionamento a um elemento redimensionável.
 * Ordem dos pontos:
 * | upperLeft |   top   | upperRight
 * | left      | element |    right
 * | lowerLeft | bottom  | lowerRight
 * 
 * @param {HTMLElement} element 
 * @param {HTMLElement} parent 
 */
function addResizePoints(element, parent) {
  initialResizeCssProperties(element, parent);

  let resizePoints = [
    "left",
    "upperLeft",
    "top",
    "upperRight",
    "right",
    "lowerRight",
    "bottom",
    "lowerLeft",
  ];
  for (let i = 0, len = resizePoints.length; i < len; i++) {
    let div = createElementWithClassName("div", resizePoints[i]);
    parent.appendChild(div);
    addResizePointFunctionality(element, parent, div);
  }
}

/**
 * Define as propriedades CSS iniciais para redimensionamento.
 * 
 * @param {HTMLElement} element 
 * @param {HTMLElement} parent 
 */
function initialResizeCssProperties(element, parent) {
  let computed = getComputedStyle(element);
  
  var deviceWidth = $(window).width();
  var deviceHeight = $(window).height();
  
  // Pega os valores computados
  let w = computed.getPropertyValue("width");
  let h = computed.getPropertyValue("height");

  // Se o valor for "0px" ou menor que o mínimo desejado, usar um valor padrão
  const minWidth = 300;  // valor mínimo de largura, se desejar
  const minHeight = 600; // valor mínimo de altura

  if (w === "0px" || parseInt(w) < minWidth) {
    w = (deviceWidth - 10) + "px";
  }
  if (h === "0px" || parseInt(h) < minHeight) {
    h = (deviceHeight - 10) + "px";
  }
  
  // Se a tela for maior que os limites, ajustar para um valor padrão
  if (deviceWidth > 800) { w = "800px"; }
  if (deviceHeight > 600) { h = "600px"; }  // ou mantenha o valor calculado se preferir

  parent.style.top = computed.getPropertyValue("top");
  parent.style.left = computed.getPropertyValue("left");
  parent.style.gridTemplateRows = "5px " + h + " 5px";
  parent.style.gridTemplateColumns = "4px " + w + " 4px";
  parent.style.backgroundColor = computed.getPropertyValue("background-color");

  element.style.top = "0px";
  element.style.left = "0px";
  element.style.width = w;
  element.style.height = h;
}


/**
 * Adiciona a funcionalidade de redimensionamento a um ponto.
 * 
 * @param {HTMLElement} element 
 * @param {HTMLElement} parent 
 * @param {HTMLElement} resizePoint 
 */
function addResizePointFunctionality(element, parent, resizePoint) {
  resizePoint.onmousedown = function (event) {
    if (event.which == 1) {
      trackMouseDragPlusAction({
        action: "resize",
        param: [element, parent, resizePoint.className],
      }, event);
    }
  };
}

/**
 * Altera tamanho e posição do elemento de acordo com o evento de redimensionamento.
 * 
 * @param {HTMLElement} element 
 * @param {HTMLElement} parent 
 * @param {String} resizePoint 
 * @param {Object} mouseDrag - Movimento total do mouse (mouseDrag.x, mouseDrag.y)
 */
function changeElementSizeAndPosition(element, parent, resizePoint, mouseDrag) {
  let zone = getResizePointZone(resizePoint);
  changeHorizontalMeasures(element, parent, mouseDrag.x, zone[0]);
  changeVerticalMeasures(element, parent, mouseDrag.y, zone[1]);
}

/**
 * Obtém as zonas horizontais e verticais do ponto de redimensionamento.
 * 
 * @param {String} resizePoint 
 * @returns {Array} [zona horizontal, zona vertical]
 */
function getResizePointZone(resizePoint) {
  return [
    getHorizontalResizePointZone(resizePoint), // Zona horizontal
    getVerticalResizePointZone(resizePoint), // Zona vertical
  ];
}

/**
 * Determina a zona horizontal para o ponto de redimensionamento.
 * 
 * @param {String} resizePoint
 */
function getHorizontalResizePointZone(resizePoint) {
  if (
    resizePoint == "left" ||
    resizePoint == "upperLeft" ||
    resizePoint == "lowerLeft"
  ) {
    return 0;
  } else if (
    resizePoint == "right" ||
    resizePoint == "upperRight" ||
    resizePoint == "lowerRight"
  ) {
    return 1;
  }
  return undefined;
}

/**
 * Determina a zona vertical para o ponto de redimensionamento.
 * 
 * @param {String} resizePoint 
 * @returns {Number|undefined} 2 para topo, 3 para base, caso contrário undefined
 */
function getVerticalResizePointZone(resizePoint) {
  if (
    resizePoint == "top" ||
    resizePoint == "upperLeft" ||
    resizePoint == "upperRight"
  ) {
    return 2;
  } else if (
    resizePoint == "bottom" ||
    resizePoint == "lowerLeft" ||
    resizePoint == "lowerRight"
  ) {
    return 3;
  }
  return undefined;
}

/**
 * Altera as medidas horizontais conforme o evento de redimensionamento.
 * 
 * @param {HTMLElement} element 
 * @param {HTMLElement} parent 
 * @param {Number} mouseDrag 
 * @param {Number} zone 
 */
function changeHorizontalMeasures(element, parent, mouseDrag, zone) {
  if (zone === undefined) {
    return;
  }
  if (zone == 1) {
    mouseDrag = -mouseDrag;
  }
  let width = parseInt(element.style.width.slice(0, -2)) + mouseDrag;
  let offsetLeft = parent.offsetLeft;
  if (zone == 1 && offsetLeft + width + 6 > document.body.clientWidth) {
    return;
  }
  if (width >= 5) {
    if (zone == 0) {
      let newOffset = offsetLeft - mouseDrag;
      if (newOffset < 0) {
        return;
      }
      parent.style.left = newOffset + "px";
    }
    element.style.width = width + "px";
    parent.style.gridTemplateColumns = "5px " + width + "px 5px";
  }
}

/**
 * Altera as medidas verticais conforme o evento de redimensionamento.
 * 
 * @param {HTMLElement} element 
 * @param {HTMLElement} parent 
 * @param {Number} mouseDrag 
 * @param {Number} zone 
 */
function changeVerticalMeasures(element, parent, mouseDrag, zone) {
  if (zone === undefined) {
    return;
  }
  if (zone == 3) {
    mouseDrag = -mouseDrag;
  }
  let height = parseInt(element.style.height.slice(0, -2)) + mouseDrag;
  let offsetTop = parent.offsetTop;
  if (zone == 3 && offsetTop + height + 5 > document.body.clientHeight) {
    return;
  }
  if (height >= 5) {
    if (zone == 2) {
      let newOffset = offsetTop - mouseDrag;
      if (newOffset < 0) {
        return;
      }
      parent.style.top = newOffset + "px";
    }
    element.style.height = height + "px";
    parent.style.gridTemplateRows = "5px " + height + "px 5px";
  }
}

/* ================= DRAGGABLE ELEMENT ================= */

/**
 * Torna um elemento arrastável.
 * Uso: clique com o botão esquerdo + arraste para mover.
 * 
 * @param {HTMLElement} element
 */
function draggable(element, title, iconSrc) { // Receive data
  element.classList.add("draggable");

  // Adiciona header que atuará como ponto de arrasto.
  let dragPoint = createElementWithIdAndClassName(
    "div",
    element.id + "_header",
    "dragPoint"
  );
  dragPoint.classList.add("w-rounded-5-t");
  initialDragPointStyling(dragPoint);

  let titleArea = createElementWithClassName('div', 'window-title-area');
  if (iconSrc) {
      let imgIcon = document.createElement("img");
      imgIcon.src = iconSrc;
      imgIcon.id = "window_icon_" + element.getAttribute("name"); // Use window name for ID
      imgIcon.style.height = "20px";
      imgIcon.style.width = "20px";
      imgIcon.classList.add("rounded-md", "mr-2");
      titleArea.appendChild(imgIcon);
  }
  if (title) {
      let windowTitle = document.createElement("span");
      windowTitle.classList.add("font-semibold", "text-sm", "text-gray-700");
      windowTitle.textContent = title;
      titleArea.appendChild(windowTitle);
  }
  dragPoint.appendChild(titleArea);

  // Insere o dragPoint como primeiro filho do elemento
  let firstChild = element.firstChild;
  if (firstChild !== null) {
    element.insertBefore(dragPoint, firstChild);
  } else {
    element.appendChild(dragPoint);
  }

  // Configuração para elementos redimensionáveis
  if (element.classList.contains("resizable")) {
    let parent = element.parentElement;
    if (parent.classList.contains("parentResize")) {
      resizePointsStyling(element, dragPoint);
      element = parent;
    }
  }

  dragPoint.onmousedown = function (event) {
    if (event.which == 1) {
      trackMouseDragPlusAction({ action: "drag", param: [element] }, event);
    }
  };
}

/**
 * Estiliza o ponto de arrasto.
 * @param {HTMLElement} dragPoint 
 */
function initialDragPointStyling(dragPoint) {
  
}

/**
 * Estiliza os pontos de redimensionamento conforme as propriedades do header.
 * @param {HTMLElement} element 
 * @param {HTMLElement} dragPoint 
 */
function resizePointsStyling(element, dragPoint) {
  for (let i = 0; i < 5; i++) {
    let sibling = element.nextSibling;
    sibling.style.backgroundColor = dragPoint.style.backgroundColor;
    element = sibling;
  }
}

/**
 * Calcula a nova posição para o elemento (impede que seja arrastado para fora da tela).
 * 
 * @param {HTMLElement} ele 
 * @param {Object} mouseDrag 
 * @returns {Object} Nova posição {x, y}
 */
function getDragNewPosition(ele, mouseDrag) {
  let element = getElementOffsetAndMeasures(ele);
  let newPosition = {
    x: element.left - mouseDrag.x,
    y: element.top - mouseDrag.y,
  };
  let boundaries = {
    left: newPosition.x,
    top: newPosition.y,
    right: newPosition.x + element.width,
    bottom: newPosition.y + element.height,
  };
  return preventDragOutsideScreen(element, newPosition, boundaries);
}

/**
 * Obtém as medidas e offset do elemento.
 * @param {HTMLElement} element 
 * @returns {Object} {left, top, width, height}
 */
function getElementOffsetAndMeasures(element) {
  return {
    left: element.offsetLeft,
    top: element.offsetTop,
    height: element.offsetHeight,
    width: element.offsetWidth,
  };
}

/**
 * Impede que o elemento seja arrastado para fora da tela.
 * 
 * @param {Object} element 
 * @param {Object} newPosition 
 * @param {Object} boundaries 
 */
function preventDragOutsideScreen(element, newPosition, boundaries) {
  let limit = getDocumentBodyLimits();
  if (boundaries.left < limit.left) {
    newPosition.x = element.left;
  }
  if (boundaries.top < limit.top) {
    newPosition.y = element.top;
  }
  if (boundaries.right > limit.right) {
    newPosition.x = element.left;
  }
  if (boundaries.bottom > limit.bottom) {
    newPosition.y = element.top;
  }
  return newPosition;
}

/**
 * Ação de arrastar ou redimensionar, utilizando a posição do mouse.
 * 
 * @param {Object} action 
 * @param {Object} mouseDrag 
 */
function dragAction(action, mouseDrag) {
  if (action.action == "resize") {
    changeElementSizeAndPosition(action.param[0], action.param[1], action.param[2], mouseDrag);
  }
  if (action.action == "drag") {
    let newPosition = getDragNewPosition(action.param[0], mouseDrag);
    action.param[0].style.left = newPosition.x + "px";
    action.param[0].style.top = newPosition.y + "px";
  }
}

/* ================= MIN, MAX & CLOSE FUNCTIONS ================= */

/**
 * Adiciona a funcionalidade de fechar ao elemento.
 * @param {HTMLElement} element 
 * @param {Boolean} icon 
 */
function close(element) {
  let svgPath = [
    {
      name: "close",
      path:
        "M23.707.293h0a1,1,0,0,0-1.414,0L12,10.586,1.707.293a1,1,0,0,0-1.414,0h0a1,1,0,0,0,0,1.414L10.586,12,.293,22.293a1,1,0,0,0,0,1.414h0a1,1,0,0,0,1.414,0L12,13.414,22.293,23.707a1,1,0,0,0,1.414,0h0a1,1,0,0,0,0-1.414L13.414,12,23.707,1.707A1,1,0,0,0,23.707.293Z",
    },
  ];

  addFunctionButton(element, svgPath);
  addCloseFunctionality(element);
}

/**
 * Adiciona a funcionalidade de fechar ao botão de fechar.
 * @param {HTMLElement} element 
 */
function addCloseFunctionality(element) {
  let closeBtn = element.querySelector('.closeBtn');
  if (closeBtn) {
    closeBtn.onclick = function() {
      closeWindow(this.closest('.interactive'));
    };
  }
}

/**
 * Adiciona funcionalidades de minimizar e maximizar a um elemento.
 * @param {HTMLElement} element 
 * @param {Boolean} icons 
 * @param {Boolean} dblClick 
 */
function minMax(element, minZone, icons, dblClick) {
  if (icons !== false) {
    let svgPath = [
      {
        name: "max",
        path:
          "M23,9a1,1,0,0,0,1-1V3a3,3,0,0,0-3-3H16a1,1,0,0,0,0,2h4.586L12,10.586,3.414,2H8A1,1,0,0,0,8,0H3A3,3,0,0,0,0,3V8A1,1,0,0,0,2,8V3.414L10.586,12,2,20.586V16a1,1,0,0,0-2,0v5a3,3,0,0,0,3,3H8a1,1,0,0,0,0-2H3.414L12,13.414,20.586,22H16a1,1,0,0,0,0,2h5a3,3,0,0,0,3-3V16a1,1,0,0,0-2,0v4.586L13.414,12,22,3.414V8A1,1,0,0,0,23,9Z",
      },
      {
        name: "min",
        path:
          "M23,23H1c-.55,0-1-.45-1-1s.45-1,1-1H23c.55,0,1,.45,1,1s-.45,1-1,1Z",
      },
    ];

    addFunctionButton(element, svgPath);
  }
  addMinimizeFunction(element, minZone, icons, dblClick);
  addFullScreenMaximizeFunction(element);
}

/**
 * Adiciona os botões e ícones para minimizar, maximizar e fechar.
 * @param {HTMLElement} element 
 * @param {Array} svgPath 
 */
function addFunctionButton(element, svgPath) {
  let className = "path";
  if (element.classList.contains("draggable")) {
    element = element.firstElementChild;
    className = "dragPath";
  }
  let container = createButtonsContainer(element);
  for (let i = 0, len = svgPath.length; i < len; i++) {
    let div = createElementWithClassName("div", svgPath[i].name + "Btn mmcBtn");
    let svg = createSvgShape({
      svg: [
        { attr: "class", value: "svgIcon" },
        { attr: "viewBox", value: "0 0 24 24" },
        { attr: "xmlns", value: "http://www.w3.org/2000/svg" },
      ],
      shape: [
        {
          shape: "path",
          attrList: [
            { attr: "d", value: svgPath[i].path },
            { attr: "class", value: className },
          ],
        },
      ],
    });
    div.appendChild(svg);
    container.appendChild(div);
  }
  element.appendChild(container);
}

/**
 * Cria o container para os botões de min/max/close.
 * @param {HTMLElement} element 
 * @returns {HTMLElement} Container
 */
function createButtonsContainer(element) {
  let container = element.querySelector('.btnContainer');
  if (container === null) {
    container = createElementWithClassName("div", "btnContainer");
  }
  return container;
}

/**
 * Adiciona a funcionalidade de minimizar ao elemento.
 * @param {HTMLElement} element 
 * @param {HTMLElement} minZone 
 * @param {Boolean} icons 
 * @param {Boolean} doubleClick 
 */
function addMinimizeFunction(element, minZone, icons, doubleClick) {
  addMinimizeArea(minZone);
  if (doubleClick !== false) {
    let targetElement = element;
    if (targetElement.classList.contains("draggable")) {
      targetElement = targetElement.firstElementChild; // This is the .dragPoint header
    }
    targetElement.ondblclick = function() { toggleMaximize(element) };
  }
  if (icons !== false) {
    let minBtn = element.querySelector('.minBtn');
    if (minBtn) {
        minBtn.onclick = minimize;
    }
  }
}

/**
 * Adiciona a área de minimização onde os elementos minimizados serão exibidos.
 * @param {HTMLElement} minZone 
 */
function addMinimizeArea(minZone) {
  if (document.getElementById("minimizeZone") == null) {
    let minArea = document.createElement("div");
    minArea.classList.add("z-index-3", "opacity-0", "ease-all-10s");
    minArea.id = "minimizeZone";
    minArea.style.background =
      "radial-gradient(at bottom, rgba(0,0,0,.5) 10%, transparent 55%)";
    if (minZone == undefined) {
      document.getElementById("desktop").append(minArea);
    } else {
      minZone.append(minArea);
    }
  }
}

/**
 * Minimiza o elemento.
 */
function minimize() {
  let element;
  if (event.type == "dblclick") {
    element = storeMinimizedElement(this);
  }
  if (event.type == "click") {
    element = storeMinimizedElement(this.parentNode.parentNode);
  }
  if (deleteDuplicatedItemsMinStorage()) {
    return;
  }
  minimizeUI(element);
}

/**
 * Ajusta a interface do desktop conforme o status das janelas.
 */
function desktop() {
  var windows = document.getElementsByClassName("parentResize");
  var desktop = document.getElementById("desktop");
  var scrim = document.getElementById("desktop-scrim");
  if (!scrim) {
    scrim = document.createElement('div');
    scrim.id = 'desktop-scrim';
    document.body.appendChild(scrim);
  }
  var minimizeZone = document.getElementById("minimizeZone");
  var length = windows.length;
  var n_minimized = 0;

  for (var i = 0; i < length; i++) {
    if (windows[i].style.display === 'none') {
      n_minimized++;
    }
  }

  if (length > 0 && n_minimized < length) { // Case 1: Some windows are open
      desktop.classList.add("h-screen");
      if (scrim) scrim.classList.add("active");
      desktop.style.pointerEvents = "auto";
      if (minimizeZone) {
          minimizeZone.style.pointerEvents = "auto";
      }
  } else if (length > 0 && n_minimized === length) { // Case 2: All windows minimized
      desktop.classList.add("h-screen"); // Keep the height
      if (scrim) scrim.classList.remove("active");
      desktop.style.pointerEvents = "none"; // Allow interaction with content behind
      if (minimizeZone) {
          minimizeZone.style.pointerEvents = "auto"; // But keep taskbar interactive
      }
  } else { // Case 3: No windows
      desktop.classList.remove("h-screen");
      if (scrim) scrim.classList.remove("active");
      desktop.style.pointerEvents = "none";
  }
}

/**
 * Armazena informações do elemento minimizado.
 * @param {HTMLElement} element 
 * @returns {HTMLElement} Elemento para ajustes na interface
 */
let minStorage = [];
function storeMinimizedElement(element) {
  let template = {
    id: element.id,
    title: element.getAttribute("name"),
  };
  if (element.classList.contains("resizable")) {
    element = element.parentNode;
  }
  if (element.classList.contains("dragPoint")) {
    element = element.parentNode;
    template.id = element.id;
    template.title = element.getAttribute("name");
    if (element.classList.contains("resizable")) {
      element = element.parentNode;
    }
  }
  minStorage.push(template);
  return element;
}

/**
 * Remove itens duplicados do armazenamento de minimizados.
 * @returns {Boolean} True se houver duplicação, senão False.
 */
let count;
function deleteDuplicatedItemsMinStorage() {
  count = minStorage.length - 1;
  if (count > 0) {
    if (minStorage[count - 1].id == minStorage[count].id) {
      minStorage.pop();
      return true;
    }
  }
  return false;
}

/**
 * Calcula a quantidade de itens que cabem no elemento.
 * @param {HTMLElement} item 
 * @param {HTMLElement} element 
 * @returns {Number} Quantidade de itens
 */
let elementWidth;
function getItemCountToFitElementByWidth(item, element) {
  if (item != null) {
    let minAreaWidth = element.clientWidth;
    let style = window.getComputedStyle(item);
    let width = parseInt(style.getPropertyValue("width").slice(0, -2));
    let marginLeft = parseInt(style.getPropertyValue("margin-left").slice(0, -2));
    let marginRight = parseInt(style.getPropertyValue("margin-right").slice(0, -2));
    elementWidth = width + marginLeft + marginRight;
    return Math.floor(minAreaWidth / elementWidth);
  }
  return undefined;
}

/**
 * Cria a representação minimizada de um elemento.
 * @param {String} id 
 * @param {String} title 
 * @returns {HTMLElement} Representação minimizada
 */
function createMinimizedElementRep(id, title) {
  let element = createElementWithIdAndClassName("button", id, "minimizedItem");
  let appIcon = createElementWithIdAndClassName("img", "icon_" + id, "rounded-md");
  element.name = title;  
  appIcon.classList.add("opacity-0", "duration-150", "ease-in-out", "shadow-md");
  setTimeout(() => {
    appIcon.src = document.getElementById("window_icon_" + title).src;
    appIcon.classList.remove("opacity-0");
  }, 150);
  appIcon.style.height = "50px";
  appIcon.style.width = "0px";
  element.appendChild(appIcon);
  element.style.width = "0px";
  element.classList.add(
    "pointer",
    "transition",
    "duration-150",
    "ease-in-out",
    "hover:scale-105",
    "border-none",
    "bg-transparent",
    "my-3",    
    "opacity-0"
  );
  setTimeout(() => {
    element.classList.remove("opacity-0");
    element.style.width = null;
    appIcon.style.width = "50px";
    element.style.padding = "5px";
  }, 150);
  return element;
}

/**
 * Ajusta a interface para elementos minimizados.
 * @param {HTMLElement} element 
 */
let dropdown, numItems;
function minimizeUI(element) {
  let minimizeArea = document.getElementById("minimizeZone");
  let minRep;

  if (dropdown === undefined) {
    numItems = getItemCountToFitElementByWidth(
      minimizeArea.firstElementChild,
      minimizeArea
    );
    if (minStorage.length > numItems) {
      horizontalRepToDropdownList(numItems, minimizeArea);
      dropdown = true;
    } else {
      minRep = createMinimizedElementRep("" + count, minStorage[count].title);
      minimizeArea.appendChild(minRep);
      minRep.onclick = maximize;
      minimizeArea.classList.remove("opacity-0");
    }
  } else if (dropdown === true) {
    let ddList = document.getElementById("dropdownList");
    addDropdownItem("" + count, minStorage[count].title, ddList);
  }

  if (minRep) {
    const windowRect = element.getBoundingClientRect();
    const iconRect = minRep.getBoundingClientRect();

    const translateX = iconRect.left - windowRect.left + (iconRect.width / 2) - (windowRect.width / 2);
    const translateY = iconRect.top - windowRect.top + (iconRect.height / 2) - (windowRect.height / 2);

    element.style.transition = 'transform 0.3s ease-in-out, opacity 0.3s ease-in-out';
    element.style.transformOrigin = 'center center';
    
    element.style.transform = `translate(${translateX}px, ${translateY}px) scale(0.1)`;
    element.style.opacity = '0';

    setTimeout(() => {
      element.style.display = 'none';
      element.style.transform = '';
      element.style.opacity = '';
      element.style.transition = '';
      desktop();
    }, 300); 

  } else {
    element.classList.add("opacity-0");
    setTimeout(() => {
      element.style.display = "none";
      element.classList.remove("opacity-0");
      desktop();
    }, 150);
  }
}

/**
 * Maximiza o elemento a partir do clique na representação minimizada.
 */
function maximize() {
  const minimizedItem = this;
  const index = parseInt(minimizedItem.id);
  const windowInfo = minStorage[index];

  if (!windowInfo) return;

  const element = document.getElementById(windowInfo.id);
  if (!element) return;

  const container = element.classList.contains("resizable")
    ? element.parentElement
    : element;

  const iconRect = minimizedItem.getBoundingClientRect();

  // Animate the icon disappearing
  minimizedItem.style.transition = 'transform 0.2s, opacity 0.2s';
  minimizedItem.style.transform = 'scale(0.5)';
  minimizedItem.style.opacity = '0';

  setTimeout(() => {
    if (minimizedItem.parentNode) {
      minimizedItem.parentNode.removeChild(minimizedItem);
    }
    // Re-index remaining items after removing one
    const minimizeArea = document.getElementById("minimizeZone");
    if (minimizeArea) {
      const items = minimizeArea.querySelectorAll('.minimizedItem');
      items.forEach((item, i) => {
        item.id = i;
      });
    }
  }, 200);

  // Clean up minStorage
  minStorage = minStorage.filter(item => item.id !== windowInfo.id);


  // Restore window with animation
  // Make container visible but hidden to get dimensions
  container.style.visibility = 'hidden';
  container.style.display = element.classList.contains("resizable") ? "grid" : "block";

  const windowWidth = container.offsetWidth;
  const windowHeight = container.offsetHeight;

  // Calculate the center of the minimized icon
  const iconCenterX = iconRect.left + (iconRect.width / 2);
  const iconCenterY = iconRect.top + (iconRect.height / 2);

  // Calculate the initial position of the window so its center aligns with the icon's center
  const initialWindowLeft = iconCenterX - (windowWidth / 2);
  const initialWindowTop = iconCenterY - (windowHeight / 2);

  // Calculate the difference from its final position (container.offsetLeft, container.offsetTop)
  const finalX = container.offsetLeft;
  const finalY = container.offsetTop;

  const translateX = initialWindowLeft - finalX;
  const translateY = initialWindowTop - finalY;
  
  container.style.transform = `translate(${translateX}px, ${translateY}px) scale(0.1)`;
  container.style.opacity = '0';
  container.style.visibility = 'visible'; // Now make it visible

  setTimeout(() => {
    container.style.transition = 'transform 0.3s ease-in-out, opacity 0.3s ease-in-out';
    container.style.transform = 'translate(0, 0) scale(1)';
    container.style.opacity = '1';
    changeStackOrder.call(element);
  }, 10);

  setTimeout(() => {
    container.style.transition = '';
    desktop();
  }, 310);

  if (dropdown === true) {
    let ddList = document.getElementById("dropdownList");
    if (ddList.childElementCount <= numItems) {
      fromDropdownToHorizontalMinimized(ddList);
    }
  }
  if (dropdown === undefined) {
    let minArea = document.getElementById("minimizeZone");
    if (minArea && minArea.childElementCount == 0) {
      minStorage.length = 0;
      minArea.classList.add("opacity-0");
    }
  }
}

/**
 * Transforma representações minimizadas horizontais em uma lista dropdown.
 * @param {Number} count 
 * @param {HTMLElement} ofArea 
 */
function horizontalRepToDropdownList(count, ofArea) {
  deleteMinimizedItems(count, ofArea);
  let dropdown = addDropdown(ofArea);
  addDropdownItems(minStorage, dropdown);
}

/**
 * Converte a lista dropdown para representações minimizadas horizontais.
 * @param {HTMLElement} ddList 
 */
function fromDropdownToHorizontalMinimized(ddList) {
  let btn = ddList.previousSibling;
  let parent = ddList.parentElement;
  parent.removeChild(btn);
  parent.removeChild(ddList);
  dropdown = undefined;
  let count = 0;
  for (let i = 0, len = minStorage.length; i < len; i++) {
    if (minStorage[i] != "") {
      minStorage[count] = { id: minStorage[i].id, title: minStorage[i].title };
      count++;
    }
  }
  minStorage.length = count;
  let minimizeArea = document.getElementById("minimizeZone");
  for (let j = 0, len = minStorage.length; j < len; j++) {
    let rep = createMinimizedElementRep("" + j, minStorage[j].title);
    minimizeArea.appendChild(rep);
    rep.onclick = maximize;
  }
}

/**
 * Remove um número de itens minimizados de um elemento.
 * @param {Number} count 
 * @param {HTMLElement} parent 
 */
function deleteMinimizedItems(count, parent) {
  for (let i = 0; i < count; i++) {
    let element = document.getElementById("" + i);
    parent.removeChild(element);
  }
}

/**
 * Adiciona um dropdown para armazenar as representações minimizadas.
 * @param {HTMLElement} element 
 * @returns {HTMLElement} Lista dropdown
 */
function addDropdown(element) {
  let btn = createElementWithIdAndClassName("button", "dropdownBtn", "dropdownBtn");
  btn.innerHTML = "&#11205;";
  btn.setAttribute("onselectstart", "return false;");
  let list = document.createElement("div");
  list.id = "dropdownList";
  element.appendChild(btn);
  element.appendChild(list);
  setTimeout(() => {
    btn.onclick = function () {
      if (list.style.display == "block") {
        list.style.display = "none";
        btn.innerHTML = "&#11205;";
      } else {
        list.style.display = "block";
        btn.innerHTML = "&#11206;";
      }
    };
  }, 150);
  return list;
}

/**
 * Adiciona itens a uma lista dropdown.
 * @param {Array} items 
 * @param {HTMLElement} dropdown 
 */
function addDropdownItems(items, dropdown) {
  for (let j = 0, len = items.length; j < len; j++) {
    addDropdownItem("" + j, items[j].title, dropdown);
  }
}

/**
 * Adiciona um item individual a uma lista dropdown.
 * @param {String} id 
 * @param {String} title 
 * @param {HTMLElement} dropdown 
 */
function addDropdownItem(id, title, dropdown) {
  let newItem = createElementWithIdAndClassName("div", id, "dropdownItem");
  newItem.textContent = title;
  newItem.setAttribute("onselectstart", "return false;");
  dropdown.appendChild(newItem);
  newItem.onclick = maximize;
}

/**
 * Adiciona funcionalidade de maximizar para tela cheia.
 * @param {HTMLElement} element 
 */
let maxStorage = {};
function toggleMaximize(element) {
    let index = "" + element.id;
    let isResizable;
    if (element.classList.contains("resizable")) {
      isResizable = true;
    }
    if (maxStorage[index] === undefined) {
      let width =
        window.innerWidth ||
        document.documentElement.clientWidth ||
        document.body.clientWidth;
      let height =
        window.innerHeight ||
        document.documentElement.clientHeight ||
        document.body.clientHeight;
      let newKey = element.id;
      if (isResizable) {
        let parent = element.parentNode;
        maxStorage[newKey] = { actualSize: getElementSizeAndPosition(parent) };
        element.style.width = "100%";
        element.style.height = "100%";
        parent.style.top = "0px";
        parent.style.left = "0px";
        parent.style.margin = "0px";
        parent.style.gridTemplateRows = "5px " + (height - 10) + "px 5px";
        parent.style.gridTemplateColumns = "5px " + (width - 10) + "px 5px";
      } else {
        maxStorage[newKey] = { actualSize: getElementSizeAndPosition(element) };
        element.style.top = "0px";
        element.style.left = "0px";
        element.style.margin = "0px";
        element.style.width = width + "px";
        element.style.height = height + "px";
      }
    } else {
      if (isResizable) {
        let parent = element.parentElement;
        parent.style.top = maxStorage[index].actualSize.top;
        parent.style.left = maxStorage[index].actualSize.left;
        parent.style.margin = maxStorage[index].actualSize.margin;
        parent.style.gridTemplateRows = maxStorage[index].actualSize.gridRow;
        parent.style.gridTemplateColumns = maxStorage[index].actualSize.gridCol;
      } else {
        element.style.top = maxStorage[index].actualSize.top;
        element.style.left = maxStorage[index].actualSize.left;
        element.style.width = maxStorage[index].actualSize.width;
        element.style.margin = maxStorage[index].actualSize.margin;
        element.style.height = maxStorage[index].actualSize.height;
      }
      delete maxStorage[index];
    }
}

function addFullScreenMaximizeFunction(element) {
  let maxBtn = getButton(element, "maxBtn");
  if (maxBtn) {
    maxBtn.onclick = function() { toggleMaximize(element) };
  }
}

/**
 * Obtém o botão (close, min ou max) do elemento.
 * @param {HTMLElement} element 
 * @param {String} btn 
 * @returns {HTMLElement} Botão
 */
function getButton(element, btn) {
    // element is the window
    const dragPoint = element.querySelector('.dragPoint');
    if (!dragPoint) return;

    const btnContainer = dragPoint.querySelector('.btnContainer');
    if (!btnContainer) return;

    return btnContainer.querySelector('.' + btn);
}

/**
 * Obtém o tamanho e posição atual de um elemento.
 * @param {HTMLElement} element 
 * @returns {Object} {width, height, top, left, gridCol, gridRow}
 */
function getElementSizeAndPosition(element) {
  let style = window.getComputedStyle(element);
  return {
    width: style.getPropertyValue("width"),
    height: style.getPropertyValue("height"),
    top: style.getPropertyValue("top"),
    left: style.getPropertyValue("left"),
    gridCol: style.getPropertyValue("grid-template-columns"),
    gridRow: style.getPropertyValue("grid-template-rows"),
  };
}

/* ================= Z-INDEX & EVENT FUNCTIONS ================= */

/**
 * Traz o elemento para frente, ajustando o z-index.
 */
function changeStackOrder() {
  let all = document.getElementsByClassName("interactive");
  for (let i = 0, len = all.length; i < len; i++) {
    let isResizable = all[i].classList.contains("resizable");
    let container = isResizable ? all[i].parentElement : all[i];

    if (all[i] == this) {
      container.style.zIndex = 2;
      container.classList.add("active");
    } else {
      container.style.zIndex = 1;
      container.classList.remove("active");
    }
  }
}

/**
 * Eventos para redimensionar a janela e carregar sem erros.
 */
window.onresize = onWindowResize;
window.onload = onWindowLoad;

function onWindowResize() {
  resizeOnWindowChange();
  updateMinimizedItemsOnWindowChange();
}

function onWindowLoad() {
  resizeOnWindowChange();
}

/**
 * Ajusta os elementos redimensionáveis quando a janela é redimensionada.
 */
function resizeOnWindowChange() {
  let parents = document.getElementsByClassName("parentResize");
  for (let i = 0, len = parents.length; i < len; i++) {
    let windowW = document.body.clientWidth;
    let windowH = document.body.clientHeight;
    let element = parents[i].firstElementChild;
    let parent = parents[i];

    // Ajuste de largura
    let leftOffset = parent.offsetLeft;
    let parentWidth = parseInt(element.style.width.slice(0, -2)) + 6;
    if (leftOffset + parentWidth > windowW) {
      let width = windowW - leftOffset - 6;
      if (width >= 5) {
        parent.style.gridTemplateColumns = "5px " + width + "px 5px";
        element.style.width = width + "px";
      } else {
        let newOffset = windowW - 5 - 6;
        parent.style.left = newOffset + "px";
      }
    }
    // Ajuste de altura
    let offsetTop = parent.offsetTop;
    let parentHeight = parseInt(element.style.height.slice(0, -2)) + 10;
    if (offsetTop + parentHeight > windowH) {
      let height = windowH - offsetTop - 10;
      if (height >= 5) {
        parent.style.gridTemplateRows = "5px " + height + "px 5px";
        element.style.height = height + "px";
      } else {
        let newOffset = windowH - 5 - 10;
        if (newOffset >= 0) {
          parent.style.top = newOffset + "px";
        }
      }
    }
  }
}

/**
 * Atualiza a exibição dos itens minimizados conforme o redimensionamento da janela.
 */
function updateMinimizedItemsOnWindowChange() {
  let minimizeArea = document.getElementById("minimizeZone");
  if (minimizeArea) {
    if (dropdown === undefined) {
      numItems = getItemCountToFitElementByWidth(
        minimizeArea.firstElementChild,
        minimizeArea
      );
      if (minStorage.length > numItems) {
        horizontalRepToDropdownList(numItems + 1, minimizeArea);
        dropdown = true;
      }
    } else {
      numItems = Math.floor(minimizeArea.clientWidth / elementWidth);
      if (minStorage.length <= numItems) {
        fromDropdownToHorizontalMinimized(minimizeArea.firstElementChild.nextSibling);
        dropdown = undefined;
      }
    }
  }
}

/* ================= HELPERS ================= */

/**
 * Cria um elemento com id e classe.
 * @param {String} tag 
 * @param {String} id 
 * @param {String} className 
 * @returns {HTMLElement}
 */
function createElementWithIdAndClassName(tag, id, className) {
  let element = document.createElement(tag);
  element.id = id;
  element.className = className;
  return element;
}

/**
 * Cria um elemento com classe.
 * @param {String} tag 
 * @param {String} className 
 * @returns {HTMLElement}
 */
function createElementWithClassName(tag, className) {
  let element = document.createElement(tag);
  element.className = className;
  return element;
}

/**
 * Retorna os limites do documento.
 * @returns {Object} {left, right, top, bottom}
 */
function getDocumentBodyLimits() {
  return {
    left: 0,
    right: document.body.clientWidth,
    top: 0,
    bottom: document.body.clientHeight,
  };
}

/**
 * Rastreia o arrasto do mouse e executa a ação correspondente.
 * @param {Object} action 
 */
function trackMouseDragPlusAction(action, mousedownEvent) {
  // Desabilita a seleção enquanto o movimento ocorrer
  disableSelection();

  // Se estiver tratando também de iframes, desabilite seus pointer events, como já implementado
  const iframes = document.querySelectorAll("iframe");
  iframes.forEach(iframe => iframe.style.pointerEvents = "none");

  let x1 = mousedownEvent.clientX;
  let y1 = mousedownEvent.clientY;
  let ticking = false;
  let latestEvent;

  const onMouseMove = function(e) {
    latestEvent = e;
    if (!ticking) {
      window.requestAnimationFrame(function() {
        if (!latestEvent) return;
        let x2 = latestEvent.clientX;
        let y2 = latestEvent.clientY;
        let mouseDrag = { x: x1 - x2, y: y1 - y2 };
        dragAction(action, mouseDrag);
        x1 = x2;
        y1 = y2;
        ticking = false;
      });
      ticking = true;
    }
  };

  const onMouseUp = function() {
    document.removeEventListener("mousemove", onMouseMove);
    document.removeEventListener("mouseup", onMouseUp);

    // Restaura seleção e pointer events
    enableSelection();
    iframes.forEach(iframe => iframe.style.pointerEvents = "auto");
  };

  document.addEventListener("mousemove", onMouseMove);
  document.addEventListener("mouseup", onMouseUp);
}



/**
 * Para o movimento de arraste.
 */
function dragMouseStop() {
  document.onmouseup = null;
  document.onmousemove = null;
}

/**
 * Cria um SVG com múltiplos shapes.
 * @param {Object} object 
 * @returns {HTMLElement} Elemento SVG
 */
function createSvgShape(object) {
  let svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  for (let h = 0, len = object.svg.length; h < len; h++) {
    svg.setAttribute(object.svg[h].attr, object.svg[h].value);
  }
  for (let i = 0, len = object.shape.length; i < len; i++) {
    let shape = document.createElementNS("http://www.w3.org/2000/svg", object.shape[i].shape);
    for (let j = 0, len = object.shape[i].attrList.length; j < len; j++) {
      shape.setAttribute(object.shape[i].attrList[j].attr, object.shape[i].attrList[j].value);
    }
    svg.appendChild(shape);
  }
  return svg;
}

/**
 * Verifica se o elemento 'child' está contido dentro de 'parent'.
 * @param {HTMLElement} parent 
 * @param {HTMLElement} child 
 * @returns {Boolean}
 */
function checkParent(parent, child) {
  if (parent.contains(child)) return true;
  return false;
}

/**
 * Fecha o elemento especificado.
 * @param {HTMLElement} el 
 */
function closeWindow(el) {
    if (!el) return; // Element not found
    let elementToRemove = el;
    if (el.parentElement && el.parentElement.classList.contains('parentResize')) {
        elementToRemove = el.parentElement;
    }
    if (elementToRemove.parentNode) {
        elementToRemove.parentNode.removeChild(elementToRemove);
    }
    desktop();
}

/**
 * Resolve a URL da imagem do ícone, tratando diferentes formatos.
 * @param {String} iconValue - Valor do ícone (URL, base64, etc.).
 * @returns {String} URL da imagem resolvida.
 */
function resolveIconSrc(iconValue) {
    const trimmed = typeof iconValue === 'string' ? iconValue.trim() : String(iconValue).trim();

    if (!trimmed) {
        return 'https://workz.com.br/images/no-image.jpg'; // Fallback
    }

    // 1. Check for full URLs (http/https)
    if (/^https?:\/\//i.test(trimmed)) {
        return trimmed;
    }
    // 2. Check for relative paths (e.g., /images/, /users/)
    if (trimmed.startsWith('/images/') || trimmed.startsWith('/users/')) {
        return trimmed;
    }
    // 3. Check for data URIs (e.g., data:image/png;base64,...)
    if (/^data:image\//i.test(trimmed)) {
        return trimmed;
    }
    // 4. Assume it's raw base64 data if it doesn't match other patterns
    try {
        // If the base64 string starts with the PNG signature, assume PNG.
        // Otherwise, default to JPEG.
        const isPng = trimmed.startsWith('iVBORw0KGgoAAA');
        const mimeType = isPng ? 'image/png' : 'image/jpeg';
        return `data:${mimeType};base64,${trimmed}`;
    } catch (e) {
        console.error("Error processing base64 icon:", e);
        return 'https://workz.com.br/images/no-image.jpg'; // Fallback on error
    }
}