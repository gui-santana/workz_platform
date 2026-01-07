// JavaScript otimizado
// Compilado em: 2025-12-30 20:15:28
// Compilador Universal - Genérico

console.log('🚀 App JavaScript iniciado (Compilador Universal)');

// Inicializar WorkzSDK se disponível
if (typeof WorkzSDK !== 'undefined') {
    console.log('🔧 WorkzSDK disponível');
    WorkzSDK.init();
}

try {
    // Executar código JavaScript
    (window.StoreApp = {
    currentDate: new Date(),
    currentView: "month",
    events: [],
    selectedDate: null,
    async bootstrap() {
        try {
            await WorkzSDK.init({ mode: "embed" });
            if (!WorkzSDK.getUser()) return void this.showError("Usuário não autenticado");
            await this.loadEvents(), this.renderApp(), this.bindEvents();
        } catch (e) {
            console.error("Erro ao inicializar:", e), this.showError("Erro ao carregar o aplicativo");
        }
    },
    async loadEvents() {
        try {
            console.log("Carregando eventos...");
            const e = await WorkzSDK.storage.docs.query({});
            if ((console.log("Resposta da query:", e), e.success)) {
                let t = [];
                e.data && Array.isArray(e.data)
                    ? (t = e.data)
                    : e.documents && Array.isArray(e.documents) && (t = e.documents),
                    (this.events = t
                        .filter((e) => e.id && e.id.startsWith("event_"))
                        .map((e) => ({ id: e.id, ...e.document }))),
                    console.log("Eventos carregados:", this.events);
            } else console.log("Nenhum evento encontrado ou erro na resposta"), (this.events = []);
        } catch (e) {
            console.error("Erro ao carregar eventos:", e), (this.events = []);
        }
    },
    renderApp() {
        const e = WorkzSDK.getUser();
        document.getElementById("app-root").innerHTML =
            `<div class="calendar-app">\n        <div class="calendar-header">\n          <div class="header-left">\n			<span class="user-info">Olá, ${e.tt}!</span>\n          </div>\n          <div class="header-right">\n            <button class="btn btn-secondary btn-sm me-2" onclick="StoreApp.debugReloadEvents()" title="Debug: Recarregar Eventos">\n              <i class="fas fa-sync"></i>\n            </button>\n            <button class="btn btn-primary" onclick="StoreApp.showNewEventModal()">\n              <i class="fas fa-plus"></i> Novo Evento\n            </button>\n          </div>\n        </div>\n\n        <div class="calendar-toolbar">\n          <div class="nav-controls">\n            <button class="btn btn-outline-secondary" onclick="StoreApp.previousPeriod()">\n              <i class="fas fa-chevron-left"></i>\n            </button>\n            <button class="btn btn-outline-secondary" onclick="StoreApp.goToToday()">Hoje</button>\n            <button class="btn btn-outline-secondary" onclick="StoreApp.nextPeriod()">\n              <i class="fas fa-chevron-right"></i>\n            </button>\n          </div>\n          \n          <div class="period-title">\n            <h3 id="period-title">${this.getPeriodTitle()}</h3>\n          </div>\n\n          <div class="view-controls">\n            <div class="btn-group" role="group">\n              <button class="btn ${"month" === this.currentView ? "btn-primary" : "btn-outline-primary"}" \n                      onclick="StoreApp.changeView('month')">Mês</button>\n              <button class="btn ${"week" === this.currentView ? "btn-primary" : "btn-outline-primary"}" \n                      onclick="StoreApp.changeView('week')">Semana</button>\n              <button class="btn ${"day" === this.currentView ? "btn-primary" : "btn-outline-primary"}" \n                      onclick="StoreApp.changeView('day')">Dia</button>\n            </div>\n          </div>\n        </div>\n\n        <div class="calendar-content">\n          <div id="calendar-view">\n            ${this.renderCalendarView()}\n          </div>\n        </div>\n\n        \x3c!-- Modal para Novo/Editar Evento --\x3e\n        <div class="modal-overlay" id="eventModal" style="display: none;">\n          <div class="modal-dialog">\n            <div class="modal-content">\n              <div class="modal-header">\n                <h5 class="modal-title" id="eventModalTitle">Novo Evento</h5>\n                <button type="button" class="btn-close" onclick="StoreApp.hideModal('eventModal')">&times;</button>\n              </div>\n              <div class="modal-body">\n                <form id="eventForm">\n                  <input type="hidden" id="eventId" value="">\n                  \n                  <div class="mb-3">\n                    <label for="eventTitle" class="form-label">Título *</label>\n                    <input type="text" class="form-control" id="eventTitle" required>\n                  </div>\n                  \n                  <div class="row">\n                    <div class="col-md-6">\n                      <label for="eventStartDate" class="form-label">Data Início *</label>\n                      <input type="date" class="form-control" id="eventStartDate" required>\n                    </div>\n                    <div class="col-md-6">\n                      <label for="eventStartTime" class="form-label">Hora Início</label>\n                      <input type="time" class="form-control" id="eventStartTime">\n                    </div>\n                  </div>\n                  \n                  <div class="row mt-3">\n                    <div class="col-md-6">\n                      <label for="eventEndDate" class="form-label">Data Fim</label>\n                      <input type="date" class="form-control" id="eventEndDate">\n                    </div>\n                    <div class="col-md-6">\n                      <label for="eventEndTime" class="form-label">Hora Fim</label>\n                      <input type="time" class="form-control" id="eventEndTime">\n                    </div>\n                  </div>\n                  \n                  <div class="mb-3 mt-3">\n                    <label for="eventDescription" class="form-label">Descrição</label>\n                    <textarea class="form-control" id="eventDescription" rows="3"></textarea>\n                  </div>\n                  \n                  <div class="mb-3">\n                    <label for="eventColor" class="form-label">Cor</label>\n                    <select class="form-control" id="eventColor">\n                      <option value="#007bff">Azul</option>\n                      <option value="#28a745">Verde</option>\n                      <option value="#dc3545">Vermelho</option>\n                      <option value="#ffc107">Amarelo</option>\n                      <option value="#6f42c1">Roxo</option>\n                      <option value="#fd7e14">Laranja</option>\n                    </select>\n                  </div>\n                  \n                  <div class="form-check">\n                    <input class="form-check-input" type="checkbox" id="eventAllDay">\n                    <label class="form-check-label" for="eventAllDay">\n                      Dia inteiro\n                    </label>\n                  </div>\n                </form>\n              </div>\n              <div class="modal-footer">\n                <button type="button" class="btn btn-danger" id="deleteEventBtn" style="display:none;" onclick="StoreApp.deleteEvent()">\n                  <i class="fas fa-trash"></i> Excluir\n                </button>\n                <button type="button" class="btn btn-secondary" onclick="StoreApp.hideModal('eventModal')">Cancelar</button>\n                <button type="button" class="btn btn-primary" onclick="StoreApp.saveEvent()">Salvar</button>\n              </div>\n            </div>\n          </div>\n        </div>\n      </div>\n\n      <style>\n        .calendar-app {\n          font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;\n          height: 100vh;\n          display: flex;\n          flex-direction: column;\n        }\n\n        .calendar-header {\n          background: #f8f9fa;\n          padding: 1rem;\n          border-bottom: 1px solid #dee2e6;\n          display: flex;\n          justify-content: space-between;\n          align-items: center;\n        }\n\n        .header-left h2 {\n          margin: 0;\n          color: #0078d4;\n          font-size: 1.5rem;\n        }\n\n        .user-info {\n          color: #6c757d;\n          font-size: 0.9rem;\n        }\n\n        .calendar-toolbar {\n          background: white;\n          padding: 1rem;\n          border-bottom: 1px solid #dee2e6;\n          display: flex;\n          justify-content: space-between;\n          align-items: center;\n          flex-wrap: wrap;\n        }\n\n        .nav-controls {\n          display: flex;\n          gap: 0.5rem;\n        }\n\n        .period-title h3 {\n          margin: 0;\n          color: #333;\n          font-size: 1.25rem;\n        }\n\n        .calendar-content {\n          flex: 1;\n          overflow: auto;\n          background: white;\n        }\n\n        .calendar-grid {\n          display: grid;\n          grid-template-columns: repeat(7, 1fr);\n          gap: 1px;\n          background: #dee2e6;\n          margin: 1rem;\n        }\n\n        .calendar-day-header {\n          background: #f8f9fa;\n          padding: 0.75rem;\n          text-align: center;\n          font-weight: 600;\n          color: #495057;\n        }\n\n        .calendar-day {\n          background: white;\n          min-height: 120px;\n          padding: 0.5rem;\n          cursor: pointer;\n          transition: background-color 0.2s;\n          position: relative;\n        }\n\n        .calendar-day:hover {\n          background: #f8f9fa;\n        }\n\n        .calendar-day.other-month {\n          background: #f8f9fa;\n          color: #6c757d;\n        }\n\n        .calendar-day.today {\n          background: #e3f2fd;\n        }\n\n        .calendar-day.selected {\n          background: #bbdefb;\n        }\n\n        .day-number {\n          font-weight: 600;\n          margin-bottom: 0.25rem;\n        }\n\n        .event-item {\n          background: #007bff;\n          color: white;\n          padding: 2px 6px;\n          margin: 1px 0;\n          border-radius: 3px;\n          font-size: 0.75rem;\n          cursor: pointer;\n          overflow: hidden;\n          text-overflow: ellipsis;\n          white-space: nowrap;\n        }\n\n        .event-item:hover {\n          opacity: 0.8;\n        }\n\n        .week-view, .day-view {\n          margin: 1rem;\n        }\n\n        .time-grid {\n          display: grid;\n          grid-template-columns: 80px 1fr;\n          gap: 1px;\n          background: #dee2e6;\n        }\n\n        .time-slot {\n          background: white;\n          padding: 0.5rem;\n          border-right: 1px solid #dee2e6;\n          font-size: 0.8rem;\n          color: #6c757d;\n        }\n\n        .time-content {\n          background: white;\n          min-height: 60px;\n          padding: 0.25rem;\n          position: relative;\n        }\n\n        .error-message {\n          color: #dc3545;\n          text-align: center;\n          padding: 2rem;\n        }\n\n        /* Modal Styles */\n        .modal-overlay {\n          position: fixed;\n          top: 0;\n          left: 0;\n          width: 100%;\n          height: 100%;\n          background: rgba(0, 0, 0, 0.5);\n          z-index: 1050;\n          display: flex;\n          align-items: center;\n          justify-content: center;\n        }\n\n        .modal-dialog {\n          background: white;\n          border-radius: 8px;\n          box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);\n          max-width: 500px;\n          width: 90%;\n          max-height: 90vh;\n          overflow-y: auto;\n        }\n\n        .modal-content {\n          padding: 0;\n        }\n\n        .modal-header {\n          padding: 1rem 1.5rem;\n          border-bottom: 1px solid #dee2e6;\n          display: flex;\n          justify-content: space-between;\n          align-items: center;\n        }\n\n        .modal-title {\n          margin: 0;\n          font-size: 1.25rem;\n          font-weight: 500;\n        }\n\n        .btn-close {\n          background: none;\n          border: none;\n          font-size: 1.5rem;\n          cursor: pointer;\n          padding: 0;\n          width: 30px;\n          height: 30px;\n          display: flex;\n          align-items: center;\n          justify-content: center;\n        }\n\n        .modal-body {\n          padding: 1.5rem;\n        }\n\n        .modal-footer {\n          padding: 1rem 1.5rem;\n          border-top: 1px solid #dee2e6;\n          display: flex;\n          justify-content: flex-end;\n          gap: 0.5rem;\n        }\n\n        @media (max-width: 768px) {\n          .calendar-toolbar {\n            flex-direction: column;\n            gap: 1rem;\n          }\n          \n          .calendar-day {\n            min-height: 80px;\n          }\n          \n          .event-item {\n            font-size: 0.7rem;\n          }\n        }\n      </style>\n    `;
    },
    renderCalendarView() {
        switch (this.currentView) {
            case "month":
            default:
                return this.renderMonthView();
            case "week":
                return this.renderWeekView();
            case "day":
                return this.renderDayView();
        }
    },
    renderMonthView() {
        const e = this.currentDate.getFullYear(),
            t = this.currentDate.getMonth(),
            n = new Date(e, t, 1),
            a = new Date(n);
        a.setDate(a.getDate() - n.getDay());
        let o = '<div class="calendar-grid">';
        ["Dom", "Seg", "Ter", "Qua", "Qui", "Sex", "Sáb"].forEach((e) => {
            o += `<div class="calendar-day-header">${e}</div>`;
        });
        const r = new Date(a);
        for (let e = 0; e < 6; e++)
            for (let e = 0; e < 7; e++) {
                const e = r.getMonth() === t,
                    n = this.isToday(r),
                    a = this.selectedDate && this.isSameDay(r, this.selectedDate),
                    i = this.getEventsForDate(r);
                let d = "calendar-day";
                e || (d += " other-month"),
                    n && (d += " today"),
                    a && (d += " selected"),
                    (o += `\n          <div class="${d}" onclick="StoreApp.selectDate('${r.toISOString()}')">\n            <div class="day-number">${r.getDate()}</div>\n            ${i.map((e) => `\n              <div class="event-item" style="background-color: ${e.color || "#007bff"}" \n                   onclick="event.stopPropagation(); StoreApp.editEvent('${e.id}')">\n                ${e.title}\n              </div>\n            `).join("")}\n          </div>\n        `),
                    r.setDate(r.getDate() + 1);
            }
        return (o += "</div>"), o;
    },
    renderWeekView() {
        const e = this.getStartOfWeek(this.currentDate),
            t = [];
        for (let n = 0; n < 7; n++) {
            const a = new Date(e);
            a.setDate(a.getDate() + n), t.push(a);
        }
        let n = '<div class="week-view"><div class="time-grid">';
        (n += '<div class="time-slot"></div>'),
            t.forEach((e) => {
                const t = this.isToday(e);
                n += `\n        <div class="calendar-day-header ${t ? "today" : ""}">\n          ${e.toLocaleDateString("pt-BR", { weekday: "short", day: "numeric" })}\n        </div>\n      `;
            });
        for (let e = 8; e <= 18; e++)
            (n += `<div class="time-slot">${e}:00</div>`),
                t.forEach((e) => {
                    const t = this.getEventsForDate(e);
                    n += `\n          <div class="time-content" onclick="StoreApp.selectDate('${e.toISOString()}')">\n            ${t.map((e) => `\n              <div class="event-item" style="background-color: ${e.color || "#007bff"}" \n                   onclick="event.stopPropagation(); StoreApp.editEvent('${e.id}')">\n                ${e.title}\n              </div>\n            `).join("")}\n          </div>\n        `;
                });
        return (n += "</div></div>"), n;
    },
    renderDayView() {
        const e = this.getEventsForDate(this.currentDate);
        let t = `\n      <div class="day-view">\n        <h4>${this.currentDate.toLocaleDateString("pt-BR", { weekday: "long", year: "numeric", month: "long", day: "numeric" })}</h4>\n        <div class="time-grid">\n    `;
        for (let n = 8; n <= 18; n++)
            t += `\n        <div class="time-slot">${n}:00</div>\n        <div class="time-content">\n          ${e
                .filter((e) => {
                    if (e.allDay) return 8 === n;
                    return new Date(e.startDate + "T" + (e.startTime || "00:00")).getHours() === n;
                })
                .map(
                    (e) =>
                        `\n            <div class="event-item" style="background-color: ${e.color || "#007bff"}" \n                 onclick="StoreApp.editEvent('${e.id}')">\n              ${e.title}\n              ${e.startTime ? ` - ${e.startTime}` : " (Dia inteiro)"}\n            </div>\n          `
                )
                .join("")}\n        </div>\n      `;
        return (t += "</div></div>"), t;
    },
    getPeriodTitle() {
        switch (this.currentView) {
            case "month":
                return this.currentDate.toLocaleDateString("pt-BR", { year: "numeric", month: "long" });
            case "week":
                const e = this.getStartOfWeek(this.currentDate),
                    t = new Date(e);
                return (
                    t.setDate(t.getDate() + 6), `${e.toLocaleDateString("pt-BR")} - ${t.toLocaleDateString("pt-BR")}`
                );
            case "day":
                return this.currentDate.toLocaleDateString("pt-BR", {
                    weekday: "long",
                    year: "numeric",
                    month: "long",
                    day: "numeric",
                });
            default:
                return "";
        }
    },
    getStartOfWeek(e) {
        const t = new Date(e);
        return t.setDate(t.getDate() - t.getDay()), t;
    },
    isToday(e) {
        const t = new Date();
        return this.isSameDay(e, t);
    },
    isSameDay: (e, t) =>
        e.getFullYear() === t.getFullYear() && e.getMonth() === t.getMonth() && e.getDate() === t.getDate(),
    getEventsForDate(e) {
        const t = e.toISOString().split("T")[0];
        return this.events.filter((e) => {
            if (!e.startDate) return !1;
            const n = e.startDate,
                a = e.endDate || e.startDate;
            return t >= n && t <= a;
        });
    },
    previousPeriod() {
        switch (this.currentView) {
            case "month":
                this.currentDate.setMonth(this.currentDate.getMonth() - 1);
                break;
            case "week":
                this.currentDate.setDate(this.currentDate.getDate() - 7);
                break;
            case "day":
                this.currentDate.setDate(this.currentDate.getDate() - 1);
        }
        this.updateView();
    },
    nextPeriod() {
        switch (this.currentView) {
            case "month":
                this.currentDate.setMonth(this.currentDate.getMonth() + 1);
                break;
            case "week":
                this.currentDate.setDate(this.currentDate.getDate() + 7);
                break;
            case "day":
                this.currentDate.setDate(this.currentDate.getDate() + 1);
        }
        this.updateView();
    },
    goToToday() {
        (this.currentDate = new Date()), this.updateView();
    },
    changeView(e) {
        (this.currentView = e), this.updateView();
    },
    updateView() {
        (document.getElementById("period-title").textContent = this.getPeriodTitle()),
            (document.getElementById("calendar-view").innerHTML = this.renderCalendarView()),
            document.querySelectorAll(".view-controls .btn").forEach((e) => {
                e.className = e.onclick.toString().includes(`'${this.currentView}'`)
                    ? "btn btn-primary"
                    : "btn btn-outline-primary";
            });
    },
    selectDate(e) {
        (this.selectedDate = new Date(e)), "month" === this.currentView && this.updateView();
    },
    showNewEventModal() {
        (document.getElementById("eventModalTitle").textContent = "Novo Evento"),
            document.getElementById("eventForm").reset(),
            (document.getElementById("eventId").value = ""),
            (document.getElementById("deleteEventBtn").style.display = "none");
        const e = this.selectedDate || this.currentDate;
        (document.getElementById("eventStartDate").value = e.toISOString().split("T")[0]), this.showModal("eventModal");
    },
    editEvent(e) {
        const t = this.events.find((t) => t.id === e);
        t &&
            ((document.getElementById("eventModalTitle").textContent = "Editar Evento"),
            (document.getElementById("eventId").value = t.id),
            (document.getElementById("eventTitle").value = t.title),
            (document.getElementById("eventStartDate").value = t.startDate),
            (document.getElementById("eventStartTime").value = t.startTime || ""),
            (document.getElementById("eventEndDate").value = t.endDate || ""),
            (document.getElementById("eventEndTime").value = t.endTime || ""),
            (document.getElementById("eventDescription").value = t.description || ""),
            (document.getElementById("eventColor").value = t.color || "#007bff"),
            (document.getElementById("eventAllDay").checked = t.allDay || !1),
            (document.getElementById("deleteEventBtn").style.display = "block"),
            this.showModal("eventModal"));
    },
    async saveEvent() {
        const e = document.getElementById("eventForm");
        if (!e.checkValidity()) return void e.reportValidity();
        const t = document.getElementById("eventId").value,
            n = !!t,
            a = {
                title: document.getElementById("eventTitle").value,
                startDate: document.getElementById("eventStartDate").value,
                startTime: document.getElementById("eventStartTime").value,
                endDate: document.getElementById("eventEndDate").value,
                endTime: document.getElementById("eventEndTime").value,
                description: document.getElementById("eventDescription").value,
                color: document.getElementById("eventColor").value,
                allDay: document.getElementById("eventAllDay").checked,
                createdAt: n ? void 0 : new Date().toISOString(),
                updatedAt: new Date().toISOString(),
            };
        try {
            const e = t || "event_" + Date.now();
            await WorkzSDK.storage.docs.save(e, a),
                await this.loadEvents(),
                this.updateView(),
                this.hideModal("eventModal"),
                this.showSuccess(n ? "Evento atualizado com sucesso!" : "Evento criado com sucesso!");
        } catch (e) {
            console.error("Erro ao salvar evento:", e), this.showError("Erro ao salvar evento");
        }
    },
    async deleteEvent() {
        const e = document.getElementById("eventId").value;
        if (e && confirm("Tem certeza que deseja excluir este evento?"))
            try {
                await WorkzSDK.storage.docs.delete(e),
                    await this.loadEvents(),
                    this.updateView(),
                    this.hideModal("eventModal"),
                    this.showSuccess("Evento excluído com sucesso!");
            } catch (e) {
                console.error("Erro ao excluir evento:", e), this.showError("Erro ao excluir evento");
            }
    },
    bindEvents() {
        document.getElementById("eventAllDay").addEventListener("change", function () {
            ["eventStartTime", "eventEndTime"].forEach((e) => {
                const t = document.getElementById(e);
                (t.disabled = this.checked), this.checked && (t.value = "");
            });
        }),
            document.getElementById("eventStartDate").addEventListener("change", function () {
                const e = document.getElementById("eventEndDate");
                e.value || (e.value = this.value);
            }),
            document.getElementById("eventModal").addEventListener("click", function (e) {
                e.target === this && StoreApp.hideModal("eventModal");
            }),
            document.addEventListener("keydown", function (e) {
                "Escape" === e.key && StoreApp.hideModal("eventModal");
            });
    },
    showError(e) {
        document.getElementById("app-root").innerHTML =
            `\n      <div class="error-message">\n        <i class="fas fa-exclamation-triangle"></i>\n        <h3>Erro</h3>\n        <p>${e}</p>\n      </div>\n    `;
    },
    showModal(e) {
        const t = document.getElementById(e);
        t && ((t.style.display = "flex"), (document.body.style.overflow = "hidden"));
    },
    hideModal(e) {
        const t = document.getElementById(e);
        t && ((t.style.display = "none"), (document.body.style.overflow = "auto"));
    },
    async debugReloadEvents() {
        console.log("=== DEBUG: Recarregando eventos ==="),
            console.log("Eventos atuais:", this.events),
            await this.loadEvents(),
            this.updateView(),
            console.log("Eventos após reload:", this.events),
            this.showSuccess(`Debug: ${this.events.length} eventos carregados`);
    },
    showSuccess(e) {
        const t = document.createElement("div");
        (t.className = "alert alert-success"),
            (t.style.cssText =
                "\n      position: fixed;\n      top: 20px;\n      right: 20px;\n      z-index: 9999;\n      background: #d4edda;\n      color: #155724;\n      border: 1px solid #c3e6cb;\n      border-radius: 4px;\n      padding: 12px 20px;\n      box-shadow: 0 2px 4px rgba(0,0,0,0.1);\n    "),
            (t.innerHTML = `\n      ${e}\n      <button type="button" onclick="this.parentNode.remove()" style="\n        background: none;\n        border: none;\n        float: right;\n        margin-left: 10px;\n        cursor: pointer;\n        font-size: 16px;\n      ">&times;</button>\n    `),
            document.body.appendChild(t),
            setTimeout(() => {
                t.parentNode && t.parentNode.removeChild(t);
            }, 5e3);
    },
}),
    "loading" === document.readyState
        ? document.addEventListener("DOMContentLoaded", () => StoreApp.bootstrap())
        : StoreApp.bootstrap();
    
    console.log('✅ App JavaScript executado com sucesso');
    
} catch (error) {
    console.error('❌ Erro na execução JavaScript:', error);
    
    // Mostrar erro na tela
    const container = document.getElementById('app-container') || document.body;
    container.innerHTML = `
        <div style="
            display: flex; align-items: center; justify-content: center; 
            height: 100vh; text-align: center; color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        ">
            <div>
                <h2>⚠️ Erro na Execução</h2>
                <p>${error.message}</p>
                <button onclick="location.reload()" style="
                    background: #4CAF50; color: white; border: none;
                    padding: 10px 20px; border-radius: 5px; cursor: pointer;
                    margin-top: 15px;
                ">Recarregar</button>
            </div>
        </div>
    `;
}