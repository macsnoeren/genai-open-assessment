document.addEventListener("DOMContentLoaded", function() {
    const modal = document.getElementById("examModal");
    const openBtn = document.getElementById("openModal");
    const closeBtn = document.querySelector(".modal .close");

    const form = document.getElementById("modalForm");
    const modalTitle = document.getElementById("modalTitle");
    const examId = document.getElementById("examId");
    const examTitle = document.getElementById("examTitle");
    const examDescription = document.getElementById("examDescription");

    // Open modal voor nieuw examen
    openBtn.onclick = function() {
	modalTitle.textContent = "Nieuw examen";
	examId.value = "";
	examTitle.value = "";
	examDescription.value = "";
	form.action = "/?action=exam_store";
	modal.style.display = "block";
    }

    // Close modal
    closeBtn.onclick = function() {
	modal.style.display = "none";
    }

    // Close als buiten modal wordt geklikt
    window.onclick = function(event) {
	if (event.target == modal) {
	    modal.style.display = "none";
	}
    }

    document.querySelectorAll('.editExam').forEach(btn => {
	btn.addEventListener('click', function(e) {
	    e.preventDefault();
	    modalTitle.textContent = "Examen bewerken";
	    examId.value = this.dataset.id;
	    examTitle.value = this.dataset.title;
	    examDescription.value = this.dataset.desc;
	    form.action = "/?action=exam_update";
	    modal.style.display = "block";
	});
    });

    
    // Eventueel: functie om te vullen voor edit (kan via knop met data attributes)
});


