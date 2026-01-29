<?php ob_start(); ?>

<div style="max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
    <h2>Systeem Initialisatie</h2>
    <p>Er zijn nog geen gebruikers gevonden. Maak het eerste account aan. Dit account wordt automatisch <strong>Admin</strong>.</p>
    
    <form method="POST" action="/?action=do_register">
        <div style="margin-bottom: 10px;">
            <label>Naam</label><br>
            <input type="text" name="name" required style="width: 100%;">
        </div>
        <div style="margin-bottom: 10px;">
            <label>Email</label><br>
            <input type="email" name="email" required style="width: 100%;">
        </div>
        <div style="margin-bottom: 10px;">
            <label>Wachtwoord</label><br>
            <input type="password" name="password" required style="width: 100%;">
        </div>
        <button type="submit" style="width: 100%; padding: 10px; background: #007bff; color: white; border: none;">Admin Account Aanmaken</button>
    </form>
</div>

<?php 
$content = ob_get_clean();
$title = "Setup";
require __DIR__ . '/../layouts/main.php'; 
?>