/* ----- Global variables ----- */
var base_url = "http://localhost/jquiz/";
// Modify this value to your Web server
var json_url = base_url + "jquiz.php?callback=?&";
var user_id = 0;
var username = "";
var type_flag = "YUA";
// User ID of current login user
var hTimer;
// Handle to the timer object
/* ----- Bind all necessary event handlers to DOM -----
 Assignment 1
 */
$(document).ready(function() {
	$("#log").bind("click", login);
	$("#reg").bind("click", register);
	$("#add").bind("click", addChoice);
	$("#rem").bind("click", removeChoice);
	$("#ans").bind("click", correctAnswer);
	$("#sub").bind("click", submitQuiz);
	$("#hlp").bind("click", showHelp);
	$("#pass").bind("keypress", function(e) {
		if (e.keyCode == 13) {
			login();
		}
	});
	initContent();
});

/* ----- Function to initialize content area ----- */
function initContent() {
	$("#question").val("");
	$("#editor").hide();
	$("#content").empty().append("<h2>Please login <span>(if you have already registered)</span>, or Register as new user.</h2>");
}

function login() {
	username = $("#name").val();
	var password = $("#pass").val();
	if (username.length == 0) {
		alert("Please input username");
	} else if (password.length == 0) {
		alert("Please input password");
	} else {
		$.ajax({
			url : json_url,
			method : 'get',
			dataType : 'json',
			data : {
				cmd : 'login',
				name : username,
				pass : password
			},
			success : function(data) {
				user_id = data.result;
				if (user_id == 0) {
					alert("Invalid username or password ! ");
				} else {
					init_quiz();
				}
			},
			error : function(data) {
				alert("error");
			}
		});
	}
}

function register() {
	var username = $("#name").val();
	var password = $("#pass").val();
	if (username.length == 0) {
		alert("Please input username");
	} else if (password.length == 0) {
		alert("Please input password");
	} else {
		$.ajax({
			url : json_url,
			method : 'get',
			dataType : 'json',
			data : {
				cmd : 'register',
				name : username,
				pass : password
			},
			success : function(data) {
				user_id = data.result;
				if (user_id == 0) {
					alert("The user name is already used by someone");
				} else {
					alert("Register successful");
				}
			},
			error : function(data) {
				alert("error");
			}
		});
	}
}

function openQuiz(quiz_id) {
	$.ajax({
		url : json_url,
		method : 'get',
		dataType : 'json',
		data : {
			cmd : 'open',
			uid : user_id,
			qid : quiz_id
		},
		success : function(data) {
			getQuizs();
		},
		error : function(data) {
			alert("error");
		}
	});
}

function closeQuiz(quiz_id) {
	$.ajax({
		url : json_url,
		method : 'get',
		dataType : 'json',
		data : {
			cmd : 'close',
			uid : user_id,
			qid : quiz_id
		},
		success : function(data) {
			getQuizs();
		},
		error : function(data) {
			alert("error");
		}
	});
}

function postChoice(quiz_id, answer) {
	$.ajax({
		url : json_url,
		method : 'get',
		dataType : 'json',
		data : {
			cmd : 'post',
			uid : user_id,
			qid : quiz_id,
			ans : answer
		},
		success : function(data) {
			getQuizs();
		},
		error : function(data) {
			alert("error");
		}
	});
}

function check(){
	type_flag = "";
	if($('#yours').is(':checked')){
		type_flag += "Y";
	}
	if($('#unanswered').is(':checked')){
		type_flag += "U";
	}
	if($('#answered').is(':checked')){
		type_flag += "A";
	}
	if($('#closed').is(':checked')){
		type_flag += "C";
	}
	getQuizs();
}

function init_quiz(){
	$("#header").empty().append("<h1>Online Quiz / Questionnaire</h1><div>Login as " + username + "   <input type='button' value='Logout' id='logout' onclick='location.reload()' /></div>");
	$("#editor").show();
	$("#content").empty().append("<h2 class='separator' id='optionBar'>Show Quizes/Quesionnaires:<input type='checkbox' id='yours' onclick='check()' checked><label for='yours'> Yours</label>&nbsp;	<input type='checkbox' id='unanswered' onclick='check()' checked><label for='unanswered'> Unanswered</label>&nbsp;	<input type='checkbox' id='answered' onclick='check()' checked><label for='answered'> Answered</label>&nbsp;<input type='checkbox' id='closed' onclick='check()' ><label for='closed'> Closed</label></h2>");
	getQuizs();
}

function getQuizs() {
	$.ajax({
		url : json_url,
		method : 'get',
		dataType : 'json',
		data : {
			cmd : 'gets',
			uid : user_id,
			flags : type_flag
		},
		success : function(data) {
			$("div").remove(".quiz");
			$.each(data, function(i, quiz) {
				$("#content").append("<div class='quiz' style='display: block;' id='" + quiz.qid + "'></div>");
				$("#" + quiz.qid).append("<em>Quiz." + quiz.qid + " : </em>" + quiz.question + " <span> ( by " + quiz.author + " )</span> ");
				$("#" + quiz.qid).append("<ol id='list" + quiz.qid + "'></ol>");
				$.each(quiz.choices, function(i, choice) {
					if (quiz.author == username) {
						$("#list" + quiz.qid).append("<li id='choice" + quiz.qid + "_" + i + "'>" + choice + " : <strong id='num" + quiz.qid + "_" + i + "' style='background-color: black;'>" + quiz.answers[i] + "</strong></li>");
						if (quiz.correct_answer == i) {
							$("#choice" + quiz.qid + "_" + i).addClass("correct");
						}
					} else {
						if (quiz.answers != null) {
							$("#list" + quiz.qid).append("<li id='choice" + quiz.qid + "_" + i + "'>" + choice + " : <strong id='num" + quiz.qid + "_" + i + "' style='background-color: black;'>" + quiz.answers[i] + "</strong></li>");
							if (quiz.correct_answer == quiz.your_answer && quiz.your_answer== i) {
								$("#choice" + quiz.qid + "_" + i).addClass("correct");
								$("#choice" + quiz.qid + "_" + i).append("<em class='yours correct'> Your Answer</em></li>");
							} else if (quiz.your_answer == i) {
								$("#choice" + quiz.qid + "_" + i).addClass("yours");
								$("#choice" + quiz.qid + "_" + i).append("<em class='yours'> Your Answer</em></li>");
							}
						} else {
							$("#list" + quiz.qid).append("<li id='choice" + quiz.qid + "_" + i + "'><input type='button' onclick='postChoice(" + quiz.qid + "," + i + ")' value='" + choice + "' /></li>");
						}
					}
				});
				if (quiz.author == username) {
					if (quiz.closed == 1) {
						$("#" + quiz.qid).append("<input type='button' onclick='openQuiz(" + quiz.qid + ");' value='Open Quiz' id='quiz_control' />");
					} else {
						$("#" + quiz.qid).append("<input type='button' onclick='closeQuiz(" + quiz.qid + ");' value='Close Quiz' id='quiz_control' />");
					}
				}
			});
		},
		error : function(data) {
			alert("error");
		}
	});
}

function getQuiz() {
	$.ajax({
		url : json_url,
		method : 'get',
		dataType : 'json',
		data : {
			cmd : 'get',
			uid : user_id,
			qid : quiz_id,
		},
		success : function(data) {
			alert("Question: " + data.question);
			$.each(data.choices, function(i, choice) {
				alert("Choice " + i + ": " + choice);
			});
		},
		error : function(data) {
			alert("error");
		}
	});
}

function addChoice() {
	var temp = 0;
	var choice = prompt("Please enter your choice", "");
	if (choice != null) {
		if ($('#choices option').length != 0) {
			$("#choices option").each(function() {
				if ($(this).val() == choice) {
					alert("This is same choice");
					temp = 1;
					return false;
				}
			});
		}
		if (temp == 0) {
			$("#choices").append("<option value='" + choice + "'>" + choice + "</option>");
		}
	}
}

function removeChoice() {
	var selected = $("#choices").val();
	if (selected == null && $('#choices option').length != 0) {
		alert("Please select choice");
	} else if ($('#choices option').length == 0) {
		alert("Please add choices");
	} else {
		$("#choices option[value='" + selected + "']").remove();
	}
}

function correctAnswer() {
	var selected = $("#choices").val();
	if (selected == null && $('#choices option').length != 0) {
		alert("Please select choice");
	} else if ($('#choices option').length == 0) {
		alert("Please add choices");
	} else {
		var correct_answer = $("#answer").val();
		if (correct_answer == null) {
			$("#choices option[value='" + selected + "']").attr('id', 'answer');
		} else {
			$("#answer").removeAttr('id');
			$("#choices option[value='" + selected + "']").attr('id', 'answer');
		}
	}
}

function submitQuiz() {
	var ques = $("#question").val();
	var choices_opt = $('#choices option').length;
	var correct_answer = $("#answer").val();

	if (ques == null) {
		alert("Please enter question");
	} else if (choices_opt == 0) {
		alert("Please enter choice");
	} else if (correct_answer == null) {
		var choice = new Array();
		var index = 0;

		$("#choices option").each(function() {
			choice[index] = $(this).val();
			index++;
		});
		correct_id = choice.indexOf(correct_answer) + 1;
		$.ajax({
			url : json_url,
			method : 'get',
			dataType : 'json',
			data : {
				cmd : 'new',
				uid : user_id,
				question : ques,
				choices : choice,
			},
			success : function(data) {
				getQuizs();
			},
			error : function(data) {
				alert("error");
			}
		});
	} else {
		var choice = new Array();
		var index = 0;
		var correct_id = 0;
		$("#choices option").each(function() {
			choice[index] = $(this).val();
			index++;
		});
		correct_id = choice.indexOf(correct_answer) + 1;
		$.ajax({
			url : json_url,
			method : 'get',
			dataType : 'json',
			data : {
				cmd : 'new',
				uid : user_id,
				question : ques,
				choices : choice,
				correct : correct_id
			},
			success : function(data) {
				getQuizs();
			},
			error : function(data) {
				alert("error");
			}
		});
	}
}

function showHelp() {
}
