const { execSync } = require('child_process');
const colors = {
    reset: "\x1b[0m", bold: "\x1b[1m", red: "\x1b[31m", green: "\x1b[32m", yellow: "\x1b[33m", cyan: "\x1b[36m"
};
function log(message, color = colors.reset) { console.log(`${color}${message}${colors.reset}`); }
function triggerAlert(title, message) {
    process.stdout.write("\u0007");
    log(`\n❌ [FAILURE] ${title}`, colors.bold + colors.red);
    log(`👉 ${message}\n`, colors.yellow);
    try { execSync(`notify-send "${title}" "${message}" --icon=dialog-error`); } catch (e) {}
}
function runCommand(command, stepName) {
    log(`\n=========================================`, colors.cyan);
    log(`🔄 Running: ${stepName}...`, colors.bold + colors.cyan);
    log(`=========================================`, colors.cyan);
    try { execSync(command, { stdio: 'inherit' }); return true; } catch (error) { return false; }
}
function main() {
    log("🚀 PhilHealth-AIM Automated Verification & Shipping Pipeline Initiated!\n", colors.bold + colors.green);
    const currentBranch = execSync('git rev-parse --abbrev-ref HEAD').toString().trim();
    if (currentBranch === 'main' || currentBranch === 'production') {
        triggerAlert("Target Branch Violation", "You are on main! Checkout to a sandbox branch first.");
        process.exit(1);
    }
    log(`✅ Current Sandbox Branch verified: [${currentBranch}]`, colors.green);
    if (!runCommand("git pull origin main --rebase", "Syncing Branch with Remote Main")) {
        triggerAlert("Git Pull Failure", "Could not sync. You might have merge conflicts.");
        process.exit(1);
    }
    log("\n🧹 Running Laravel Pint Style Auto-Janitor...", colors.yellow);
    runCommand("./vendor/bin/pint", "Laravel Pint Formatting");
    const gitStatus = execSync('git status --porcelain').toString().trim();
    if (gitStatus.includes('.php')) {
        log("\n📝 Staging and committing style updates...", colors.yellow);
        execSync('git add .');
        execSync('git commit -m "style: automated formatting via Laravel Pint"');
    }
    if (!runCommand("./scripts/check-file-sizes.sh", "Monolith Size Detector")) {
        triggerAlert("Monolith Detected", "A file exceeds 500 lines. Refactor before pushing!");
        process.exit(1);
    }
    let testCommand = "./sail test";
    try { execSync('docker ps | grep sail', { stdio: 'ignore' }); } catch (e) {
        log("\n⚠️ Docker Sail is not running. Falling back to local artisan test...", colors.yellow);
        testCommand = "php artisan test";
    }
    if (!runCommand(testCommand, "Execution of Test Suite")) {
        triggerAlert("Test Failure", "Fix failing tests before pushing.");
        process.exit(1);
    }
    log("\n📦 All checks passed! Preparing sandbox push...", colors.green);
    runCommand("git add .", "Staging Final Changes");
    const finalChanges = execSync('git status --porcelain').toString().trim();
    if (finalChanges) { runCommand('git commit -m "refactor: verified clean structural updates"', "Committing Changes"); }
    if (!runCommand(`git push origin ${currentBranch}`, "Pushing Sandbox Branch to Git Server")) {
        triggerAlert("Push Failed", "Could not push to origin.");
        process.exit(1);
    }
    log("\n======================================================================", colors.green);
    log("🎉 SUCCESS: Pipeline checks passed, code formatted, and branch pushed!", colors.bold + colors.green);
    log("======================================================================", colors.green);
    log(`\n🔗 Open this link in your browser to complete the Pull Request into Main:`);
    log(`👉 https://github.com/charles/philhealth-aim/pull/new/${currentBranch}\n`, colors.bold + colors.cyan);
}
main();
