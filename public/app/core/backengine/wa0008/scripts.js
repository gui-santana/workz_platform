const zeroPad = (num, places) => String(num).padStart(places, '0')

let today = new Date();
let currentMonth = today.getMonth();
let currentYear = today.getFullYear();
let selectYear = today.getYear();
let selectMonth = today.getMonth();
/*
let selectYear = document.getElementById("year");
let selectMonth = document.getElementById("month");
*/
let months = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

let monthAndYear = document.getElementById("monthAndYear");
showCalendar(currentMonth, currentYear);


function next() {
    currentYear = (currentMonth === 11) ? currentYear + 1 : currentYear;
    currentMonth = (currentMonth + 1) % 12;
    showCalendar(currentMonth, currentYear);
}

function previous() {
    currentYear = (currentMonth === 0) ? currentYear - 1 : currentYear;
    currentMonth = (currentMonth === 0) ? 11 : currentMonth - 1;
    showCalendar(currentMonth, currentYear);
}
/*
function jump() {
    currentYear = parseInt(selectYear.value);
    currentMonth = parseInt(selectMonth.value);
    showCalendar(currentMonth, currentYear);
}
*/
function showCalendar(month, year) {

    let firstDay = (new Date(year, month)).getDay();
    let daysInMonth = 32 - new Date(year, month, 32).getDate();

    let tbl = document.getElementById("calendar-body"); // body of the calendar

    // clearing all previous cells	
    tbl.innerHTML = "";

    // filing data about month and in the page via DOM.
    monthAndYear.innerHTML = months[month] + " " + year;
    selectYear.value = year;
    selectMonth.value = month;

    // creating all cells
    let date = 1;
    for (let i = 0; i < 6; i++) {		
        // creates a table row
        let row = document.createElement("tr");

        //creating individual cells, filing them up with data.
        for (let j = 0; j < 7; j++) {		
			
			let cell = document.createElement("td");
			let cellContainer = document.createElement("div");
			let cellContent = document.createElement("div");
			cell.classList.add('cm-pad-5', 'text-center');
			cellContainer.classList.add('large-12', 'w-square', 'position-relative');
			cellContent.classList.add('w-circle', 'w-square-content', 'w-shadow', 'pointer',  'abs-l-0', 'w-bkg-wh-to-gr', 'display-center-general-container');
            
			if (i === 0 && j < firstDay){
                let cellText = document.createTextNode("");
				cellContent.classList.remove('w-bkg-wh-to-gr', 'pointer');
				cellContent.classList.add('background-gray');	
                cellContent.appendChild(cellText);
				cellContainer.appendChild(cellContent);
				cell.appendChild(cellContainer);
                row.appendChild(cell);
            }else if(date > daysInMonth){				
                break;				
            }else{
                let cellText = document.createTextNode(date);
				cellContent.innerHTML = '<a class="centered">' + date + '</a>';
                if(date === today.getDate() && year === today.getFullYear() && month === today.getMonth()){
					cellContent.classList.remove('w-bkg-wh-to-gr');
					cellContent.classList.add('background-orange', 'white', 'font-weight-600');					
                }// color today's date
				cellContent.addEventListener('click', 
				function(){
					goTo('core/backengine/wa0008/dateInfo.php', 'dateInfo', year + '-' + zeroPad((month + 1), 2) + '-' + zeroPad(this.innerText, 2), '');
					daySelect(this);
				}
				);
				cellContainer.appendChild(cellContent);
				cell.appendChild(cellContainer);
                row.appendChild(cell);
                date++;
            }
        }
		

        tbl.appendChild(row); // appending each row into calendar body.		
		tbl.classList.add('fade');
		setTimeout(tbl.classList.remove('fade'), 250000);
    }
}
function daySelect(el){
	var calendar = document.getElementById('calendar');
	var selected = calendar.getElementsByClassName('background-dark');
	var today = calendar.getElementsByClassName('background-orange');	
	for(i=0; i < selected.length; i++){
		selected[i].classList.add('w-bkg-wh-to-gr');
		selected[i].classList.remove('background-dark', 'white');		
	}
	for(i=0; i < today.length; i++){
		today[i].classList.add('w-bkg-wh-to-gr', 'orange');
		today[i].classList.remove('background-orange', 'white');
	}	
	if(el.classList.contains('orange')){
		el.classList.remove('w-bkg-wh-to-gr', 'orange');
		el.classList.add('background-orange', 'white');
	}else{
		el.classList.remove('w-bkg-wh-to-gr');
		el.classList.add('background-dark', 'white');
	}	
}