
<span class="icon delete swal_delete_button"> <i class="fa-regular fa-trash-can"></i> </span>
<form method='post' action='{{$url}}' class="d-none">
<input type='hidden' name='_token' value='{{csrf_token()}}'>
<br>
<button type="submit" class="swal_form_submit_btn d-none"></button>
 </form>
