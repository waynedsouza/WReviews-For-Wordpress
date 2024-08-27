
ajaxurl = '/wp-admin/admin-ajax.php'; // Define ajaxurl
jQuery(document).ready(function($) {

    $('#add-review-link').on('click', function(e) {
        e.preventDefault();
        $('#add-review-form').toggle();
    });

    $('#submit-review').on('click', function() {
        console.log("submitting review begin");
        var reviewText = $('#review-text').val();
        var postId = $('#review-system').data('post-id');
        console.log(reviewText);
        console.log("postId" ,postId);
        console.log("submitting review posting");
        var ajaxurl = '/wp-admin/admin-ajax.php'; // Define ajaxurl
        console.log("ajaxurl" ,ajaxurl);
        $.post(ajaxurl, {
            action: 'submit_review',
            post_id: postId,
            review: reviewText
        }, function(response) {
            console.log("submitting review over");
            alert(response.data.message);
            if (response.success) {
                location.reload();
            }
        });
    });

    $('.upvote, .downvote').on('click', function() {
        var reviewId = $(this).closest('.review').data('id');
        var voteAction = $(this).hasClass('upvote') ? 'upvote' : 'downvote';
        var postId = $('#review-system').data('post-id');
        var ajaxurl = '/wp-admin/admin-ajax.php'; // Define ajaxurl
        $.post(ajaxurl, {
            action: 'vote_review',
            review_id: reviewId,
            vote_action: voteAction,
            post_id: postId
        }, function(response) {
            if (response.success) {
                $(this).text(voteAction === 'upvote' ? 'Upvote (' + response.data.new_count + ')' : 'Downvote (' + response.data.new_count + ')');
            } else {
                alert(response.data.message);
            }
        });
    });
});
